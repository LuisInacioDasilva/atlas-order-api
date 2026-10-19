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

    public function index(): JsonResponse
    {
        return response()->json(Order::query()->latest()->paginate(20));
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
