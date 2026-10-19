<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderController
{
    public function __construct(private readonly QuoteService $quotes, private readonly OrderService $orders) {}

    private function owner(Request $request): string
    {
        if (! $request->session()->has('commerce_owner')) {
            $request->session()->put('commerce_owner', Str::random(64));
        }
        return hash_hmac('sha256', $request->session()->get('commerce_owner'), (string) config('app.key'));
    }

    public function quote(QuoteRequest $request): JsonResponse
    {
        $this->owner($request);
        return response()->json($this->quotes->quote($request->validated('items')))->header('Cache-Control', 'private, no-store');
    }

    public function store(OrderRequest $request): JsonResponse
    {
        return response()->json($this->orders->create($request->validated(), $this->owner($request)), 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->orders->owned($id, $this->owner($request)))
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_active && in_array($request->user()?->role, ['admin', 'editor'], true), 403);
        $validated = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $page = DB::table('orbita_orders')->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(20, ['id'], 'page', (int) ($validated['page'] ?? 1));
        return response()->json([
            'data' => $page->getCollection()->map(fn ($order): array => $this->orders->present($order->id, true))->all(),
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
