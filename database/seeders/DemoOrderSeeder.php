<?php

namespace Database\Seeders;

use App\Services\OrderService;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('A carga de demonstração só roda em ambiente local.');
        }

        $service = app(OrderService::class);
        $samples = [
            ['Ana Lima', 'ana@example.test', 12990, 'approved'],
            ['Bruno Reis', 'bruno@example.test', 24800, 'pending'],
            ['Camila Costa', 'camila@example.test', 7850, 'fulfilled'],
            ['Daniel Rocha', 'daniel@example.test', 46000, 'cancelled'],
        ];

        foreach ($samples as $index => [$name, $email, $amount, $target]) {
            $result = $service->create([
                'customer_name' => $name,
                'customer_email' => $email,
                'amount_cents' => $amount,
            ], "demo-order-{$index}", 'demo-seeder');

            $order = $result['order'];
            if ($order->status !== 'pending' || $target === 'pending') {
                continue;
            }

            $order = $service->transition($order->id, $target === 'fulfilled' ? 'approved' : $target, 1, 'demo-seeder');
            if ($target === 'fulfilled') {
                $service->transition($order->id, 'fulfilled', $order->version, 'demo-seeder');
            }
        }
    }
}
