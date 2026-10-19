<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'order_event_id', 'topic', 'payload', 'state', 'ack_actor', 'acked_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'acked_at' => 'datetime'];
    }
}
