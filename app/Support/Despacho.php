<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Datos del despacho que salen en facturas y correos. Se toman de los ajustes del panel (Configuración) y, si no
 * están, de los datos legales y de contacto de la web. Un dato de relleno («PENDIENTE…») no se imprime nunca.
 */
class Despacho
{
    /** @return array{nombre: string, nif: string, direccion: string, telefono: string, email: string} */
    public static function datos(): array
    {
        $pick = fn (?string $setting, ?string $fallback) => collect([$setting, $fallback])
            ->first(fn ($value) => ! Readiness::isPlaceholder($value), '');

        return [
            'nombre' => $pick(Setting::getValue('company_name'), config('legal.legal_entity_name')) ?: 'Nortia',
            'nif' => $pick(Setting::getValue('company_nif'), config('legal.legal_tax_id')),
            'direccion' => $pick(Setting::getValue('company_address'), config('legal.legal_address')),
            'telefono' => $pick(Setting::getValue('company_phone'), config('site.phone')),
            'email' => $pick(Setting::getValue('company_email'), config('site.email')),
        ];
    }

    /** Línea de pie para los correos: nombre, y los datos de contacto que existan. */
    public static function lineaDeContacto(): string
    {
        $d = self::datos();

        return collect([$d['direccion'], $d['telefono'] ? 'Tel: '.$d['telefono'] : '', $d['email']])->filter()->implode(' | ');
    }
}
