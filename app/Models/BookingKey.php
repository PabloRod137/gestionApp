<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Clave de un solo uso con la que se reserva una consulta ya pagada. Solo se guarda su hash. */
class BookingKey extends Model
{
    public $timestamps = false;

    protected $fillable = ['key_hash', 'order_id', 'product_id', 'idx', 'expires_at', 'used_at'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];
}
