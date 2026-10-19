<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('customer_name', 120);
            $table->string('customer_email', 254);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('BRL');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->string('idempotency_key', 100)->unique();
            $table->char('request_hash', 64);
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('actor', 100);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('orders');
    }
};
