<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubscriptionController
{
    public function __invoke(Request $request, Outbox $outbox): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254']]);
        $email = mb_strtolower(trim($input['email']));
        DB::transaction(function () use ($email, $outbox): void {
            DB::table('orbita_subscriptions')->insertOrIgnore([
                'email' => $email, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $id = DB::table('orbita_subscriptions')->where('email', $email)->value('id');
            abort_unless($id, 503);
            $outbox->enqueue('subscription.created', (string) $id, ['subscriptionId' => $id]);
        }, 3);
        return response()->json(['message' => 'Подписка принята.', 'status' => 'pending'], 202)->header('Cache-Control', 'private, no-store');
    }
}
