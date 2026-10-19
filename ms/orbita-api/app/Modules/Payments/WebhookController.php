<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class WebhookController
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 65536, 413);
        $input = $request->validate([
            'type' => ['required', 'in:notification'],
            'event' => ['required', 'in:payment.succeeded,payment.canceled,payment.waiting_for_capture'],
            'object' => ['required', 'array'], 'object.id' => ['required', 'uuid'],
            'object.metadata' => ['nullable', 'array'], 'object.metadata.payment_id' => ['nullable', 'uuid'],
        ]);
        try {
            $payments->webhook($input);
        } catch (Throwable) {
            // Provider should retry if verification is unavailable; no unverified state is saved.
            return response()->json(['message' => 'Payment verification is temporarily unavailable.'], 503);
        }
        return response()->json(['received' => true]);
    }
}
