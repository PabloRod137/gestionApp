<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ExpedientDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Descarga de forma segura un documento asociado a un expediente del cliente.
     */
    public function download($id)
    {
        $client = Auth::user()->client;

        if (!$client) {
            abort(403, 'No tienes un perfil de cliente asociado.');
        }

        // Buscar el documento con su expediente para validar pertenencia
        $documento = ExpedientDocument::with('expedient')->findOrFail($id);

        // Validar que el expediente pertenece al cliente y que el documento es visible para él
        if ($documento->expedient->client_id !== $client->id) {
            abort(403, 'No tienes permisos para descargar este archivo.');
        }

        if ($documento->visibilidad !== 'cliente') {
            abort(403, 'Este documento no está disponible para descarga de clientes.');
        }

        // Comprobar si el archivo físico existe en storage/app
        // En desarrollo, el archivo podría no existir físicamente si es seed de prueba
        if (!Storage::exists($documento->ruta)) {
            // Fallback elegante para archivos de prueba
            return back()->with('error', 'El archivo no está disponible físicamente en el servidor de almacenamiento. Ponte en contacto con tu asesor.');
        }

        return Storage::download($documento->ruta, $documento->nombre);
    }
}
