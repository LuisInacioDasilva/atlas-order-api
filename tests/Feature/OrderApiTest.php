<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.atlas.token', 'test-secret');
        config()->set('services.atlas.client', 'tests');
    }

    private function headers(string $key = 'request-001'): array
    {
        return [
            'Authorization' => 'Bearer test-secret',
            'Idempotency-Key' => $key,
        ];
    }

    private function payload(): array
    {
        return [
            'customer_name' => 'Maria Souza',
            'customer_email' => 'maria@example.test',
            'amount_cents' => 12500,
        ];
    }

    public function test_requires_a_configured_token(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
        config()->set('services.atlas.token', null);
        $this->getJson('/api/orders')->assertStatus(503);
    }

    public function test_create_and_replay_keep_a_single_order_and_a_single_event(): void
    {
        $first = $this->postJson('/api/orders', $this->payload(), $this->headers())->assertCreated();
        $second = $this->postJson('/api/orders', $this->payload(), $this->headers())->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_events', 1);
        $this->assertDatabaseHas('order_events', ['actor' => 'tests', 'to_status' => 'pending']);
    }

    public function test_reusing_key_for_different_payload_returns_conflict(): void
    {
        $this->postJson('/api/orders', $this->payload(), $this->headers())->assertCreated();
        $this->postJson('/api/orders', [...$this->payload(), 'amount_cents' => 9900], $this->headers())
            ->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_transitions_are_versioned_and_audited(): void
    {
        $order = $this->postJson('/api/orders', $this->payload(), $this->headers())->assertCreated();
        $id = $order->json('id');

        $this->postJson("/api/orders/{$id}/transitions", [
            'status' => 'approved', 'expected_version' => 1,
        ], $this->headers())->assertOk()->assertJsonPath('version', 2);

        $this->postJson("/api/orders/{$id}/transitions", [
            'status' => 'fulfilled', 'expected_version' => 1,
        ], $this->headers())->assertStatus(409);

        $this->postJson("/api/orders/{$id}/transitions", [
            'status' => 'fulfilled', 'expected_version' => 2,
        ], $this->headers())->assertOk()->assertJsonPath('status', 'fulfilled');

        $this->postJson("/api/orders/{$id}/transitions", [
            'status' => 'cancelled', 'expected_version' => 3,
        ], $this->headers())->assertStatus(409);

        $this->assertSame(3, Order::findOrFail($id)->version);
        $this->assertDatabaseCount('order_events', 3);
    }

    public function test_invalid_input_does_not_write_data(): void
    {
        $this->postJson('/api/orders', [...$this->payload(), 'amount_cents' => -1], $this->headers())
            ->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_list_filters_and_paginates_without_mixing_customers(): void
    {
        $this->postJson('/api/orders', $this->payload(), $this->headers('first'))->assertCreated();
        $this->postJson('/api/orders', [
            ...$this->payload(), 'customer_name' => 'João',
            'customer_email' => 'joao@example.test', 'amount_cents' => 9900,
        ], $this->headers('second'))->assertCreated();

        $this->withHeaders($this->headers())->getJson('/api/orders?customer_email=MARIA@example.test&min_amount_cents=12000')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.customer_name', 'Maria Souza');
        $this->withHeaders($this->headers())->getJson('/api/orders?max_amount_cents=10000&per_page=1')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.customer_name', 'João');
        $this->withHeaders($this->headers())->getJson('/api/orders?min_amount_cents=200&max_amount_cents=100')
            ->assertUnprocessable();
        $this->withHeaders($this->headers())->getJson('/api/orders?per_page=1000')
            ->assertUnprocessable();
    }

    public function test_outbox_is_transactional_idempotent_and_contains_no_customer_data(): void
    {
        $order = $this->postJson('/api/orders', $this->payload(), $this->headers())->assertCreated();
        $this->postJson('/api/orders', $this->payload(), $this->headers())->assertOk();
        $this->assertDatabaseCount('outbox_messages', 1);

        $this->postJson("/api/orders/{$order->json('id')}/transitions", [
            'status' => 'approved', 'expected_version' => 1,
        ], $this->headers())->assertOk();
        $this->postJson("/api/orders/{$order->json('id')}/transitions", [
            'status' => 'fulfilled', 'expected_version' => 1,
        ], $this->headers())->assertStatus(409);
        $this->assertDatabaseCount('outbox_messages', 2);

        $pending = $this->withHeaders($this->headers())->getJson('/api/outbox?state=pending')
            ->assertOk()->assertJsonPath('total', 2);
        $first = $pending->json('data.0');
        $this->assertSame('order.status.changed', $first['topic']);
        $this->assertSame($order->json('id'), $first['payload']['order_id']);
        $this->assertArrayNotHasKey('customer_email', $first['payload']);
        $this->assertArrayNotHasKey('customer_name', $first['payload']);
        $this->assertArrayNotHasKey('amount_cents', $first['payload']);

        $ack = $this->postJson("/api/outbox/{$first['id']}/ack", [], $this->headers())
            ->assertOk()->assertJsonPath('state', 'acked')->assertJsonPath('ack_actor', 'tests');
        $this->postJson("/api/outbox/{$first['id']}/ack", [], $this->headers())
            ->assertOk()->assertJsonPath('acked_at', $ack->json('acked_at'));
        $this->withHeaders($this->headers())->getJson('/api/outbox?state=pending')
            ->assertOk()->assertJsonPath('total', 1);
        $this->withHeaders($this->headers())->getJson('/api/outbox?per_page=101')->assertUnprocessable();
    }
}
