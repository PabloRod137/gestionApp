<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Contador correlativo de una serie de facturas (F2026, R2026…). Sin huecos: solo avanza al emitir. */
class SerieFactura extends Model
{
    protected $table = 'series_facturas';

    protected $guarded = [];
}
