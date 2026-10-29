<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Muestra las configuraciones del sistema (solo administradores).
     */
    public function index()
    {
        $configuraciones = Setting::all()->pluck('valor', 'clave')->toArray();
        return view('panel.configuracion.index', compact('configuraciones'));
    }

    /**
     * Guarda / Actualiza los parámetros globales de la aplicación.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_nif' => 'nullable|string|max:20',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email|max:255',
            'company_address' => 'nullable|string|max:255',
            'seo_meta_title' => 'nullable|string|max:255',
            'seo_meta_description' => 'nullable|string|max:500',
            'social_twitter' => 'nullable|url|max:255',
            'social_linkedin' => 'nullable|url|max:255',
        ], [
            'company_name.required' => 'El nombre comercial del despacho es obligatorio.',
            'company_email.email' => 'Introduce un email de contacto válido.',
            'social_twitter.url' => 'El enlace de Twitter/X debe ser una URL válida.',
            'social_linkedin.url' => 'El enlace de LinkedIn debe ser una URL válida.',
        ]);

        foreach ($validated as $clave => $valor) {
            Setting::setValue($clave, $valor);
        }

        return redirect()->route('panel.configuracion.index')
            ->with('success', 'Configuración general guardada correctamente.');
    }
}
