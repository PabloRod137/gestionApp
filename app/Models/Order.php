<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pedido de la tienda. El id es público (PED-AAAAMMDD-XXXXXXXX) y el pago lo confirma siempre el webhook de Stripe. */
class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'client_id', 'user_id', 'email', 'status', 'amount_cents', 'currency',
        'stripe_session_id', 'note', 'access_hash', 'paid_at',
    ];

    protected $casts = ['paid_at' => 'datetime', 'amount_cents' => 'integer'];

    protected $hidden = ['access_hash'];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
