<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('order_event_id')->unique()->constrained('order_events')->restrictOnDelete();
            $table->string('topic', 80);
            $table->json('payload');
            $table->string('state', 20)->default('pending');
            $table->string('ack_actor', 100)->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
