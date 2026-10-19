<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'customer_name', 'customer_email', 'amount_cents', 'currency',
        'status', 'version', 'idempotency_key', 'request_hash',
    ];

    protected $hidden = ['idempotency_key', 'request_hash'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'version' => 'integer'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }
}
