<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OrderConflict;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'fulfilled', 'cancelled'])],
            'customer_email' => ['sometimes', 'email', 'max:254'],
            'min_amount_cents' => ['sometimes', 'integer', 'min:0'],
            'max_amount_cents' => ['sometimes', 'integer', 'min:0'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if (isset($filters['min_amount_cents'], $filters['max_amount_cents'])
            && $filters['min_amount_cents'] > $filters['max_amount_cents']) {
            return response()->json(['error' => 'O valor mínimo não pode exceder o máximo.'], 422);
        }

        $query = Order::query();
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['customer_email'])) {
            $query->where('customer_email', mb_strtolower(trim($filters['customer_email'])));
        }
        if (isset($filters['min_amount_cents'])) {
            $query->where('amount_cents', '>=', $filters['min_amount_cents']);
        }
        if (isset($filters['max_amount_cents'])) {
            $query->where('amount_cents', '<=', $filters['max_amount_cents']);
        }

        return response()->json($query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20)->withQueryString());
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load('events'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:120'],
            'customer_email' => ['required', 'email', 'max:254'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['sometimes', Rule::in(['BRL', 'USD', 'EUR'])],
        ]);
        $key = trim((string) $request->header('Idempotency-Key'));
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'max:100']])->validate();

        try {
            $result = $this->orders->create($data, $key, $this->actor());
        } catch (OrderConflict $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }

        return response()->json($result['order']->load('events'), $result['created'] ? 201 : 200);
    }

    public function transition(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'fulfilled', 'cancelled'])],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $updated = $this->orders->transition(
                $order->id,
                $data['status'],
                (int) $data['expected_version'],
                $this->actor(),
            );
        } catch (OrderConflict $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }

        return response()->json($updated->load('events'));
    }

    private function actor(): string
    {
        return mb_substr((string) config('services.atlas.client', 'local-client'), 0, 100);
    }
}
