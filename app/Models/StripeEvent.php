<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Eventos de Stripe ya procesados (el webhook los recibe más de una vez). */
class StripeEvent extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'event_id';

    protected $keyType = 'string';

    protected $fillable = ['event_id', 'type', 'received_at'];
}
