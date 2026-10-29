<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;

    // Campos rellenables en masa
    protected $fillable = [
        'clave',
        'valor'
    ];

    /**
     * Obtiene el valor de una configuración por su clave.
     * 
     * @param string $clave
     * @param mixed $default
     * @return mixed
     */
    public static function getValue(string $clave, $default = null)
    {
        $setting = self::where('clave', $clave)->first();
        return $setting ? $setting->valor : $default;
    }

    /**
     * Establece o actualiza el valor de una configuración.
     * 
     * @param string $clave
     * @param string|null $valor
     * @return Setting
     */
    public static function setValue(string $clave, ?string $valor)
    {
        return self::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor]
        );
    }
}
