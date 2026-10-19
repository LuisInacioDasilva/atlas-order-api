<?php

namespace App\Services;

use App\Exceptions\OrderConflict;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OutboxMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    private const ALLOWED = [
        'pending' => ['approved', 'cancelled'],
        'approved' => ['fulfilled', 'cancelled'],
        'fulfilled' => [],
        'cancelled' => [],
    ];

    /** @return array{order: Order, created: bool} */
    public function create(array $data, string $key, string $actor): array
    {
        $payload = [
            'customer_name' => trim($data['customer_name']),
            'customer_email' => mb_strtolower(trim($data['customer_email'])),
            'amount_cents' => (int) $data['amount_cents'],
            'currency' => $data['currency'] ?? 'BRL',
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($payload, $key, $hash, $actor): array {
                if ($existing = Order::where('idempotency_key', $key)->first()) {
                    return $this->replay($existing, $hash);
                }

                $order = Order::create([
                    ...$payload,
                    'id' => (string) Str::uuid(),
                    'status' => 'pending',
                    'version' => 1,
                    'idempotency_key' => $key,
                    'request_hash' => $hash,
                ]);
                $event = OrderEvent::create([
                    'order_id' => $order->id,
                    'actor' => $actor,
                    'from_status' => null,
                    'to_status' => 'pending',
                ]);
                $this->recordOutbox($event, $order);

                return ['order' => $order, 'created' => true];
            });
        } catch (QueryException $exception) {
            // A unique constraint also handles two requests racing with the same key.
            if ($existing = Order::where('idempotency_key', $key)->first()) {
                return $this->replay($existing, $hash);
            }
            throw $exception;
        }
    }

    public function transition(string $id, string $target, int $expectedVersion, string $actor): Order
    {
        return DB::transaction(function () use ($id, $target, $expectedVersion, $actor): Order {
            $order = Order::query()->lockForUpdate()->findOrFail($id);

            if ($order->status === $target) {
                return $order;
            }
            if ($order->version !== $expectedVersion) {
                throw new OrderConflict('O pedido mudou desde a última leitura. Recarregue e tente novamente.');
            }
            if (! in_array($target, self::ALLOWED[$order->status] ?? [], true)) {
                throw new OrderConflict("Transição de {$order->status} para {$target} não permitida.");
            }

            $previous = $order->status;
            $order->update(['status' => $target, 'version' => $order->version + 1]);
            $event = OrderEvent::create([
                'order_id' => $order->id,
                'actor' => $actor,
                'from_status' => $previous,
                'to_status' => $target,
            ]);
            $this->recordOutbox($event, $order);

            return $order->refresh();
        });
    }

    /** @return array{order: Order, created: bool} */
    private function replay(Order $order, string $hash): array
    {
        if (! hash_equals($order->request_hash, $hash)) {
            throw new OrderConflict('Esta chave de idempotência já foi usada com outro conteúdo.');
        }

        return ['order' => $order, 'created' => false];
    }

    private function recordOutbox(OrderEvent $event, Order $order): void
    {
        OutboxMessage::create([
            'id' => (string) Str::uuid(),
            'order_event_id' => $event->id,
            'topic' => 'order.status.changed',
            'payload' => [
                'event_id' => $event->id,
                'order_id' => $order->id,
                'status' => $order->status,
                'version' => $order->version,
            ],
            'state' => 'pending',
        ]);
    }
}
