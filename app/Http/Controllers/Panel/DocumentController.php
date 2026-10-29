<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Sube y asocia un documento a un expediente.
     */
    public function store(Request $request, Expedient $expedient)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'visibilidad' => 'required|in:interno,cliente',
            'archivo' => 'required|file|max:10240', // Máximo 10MB
        ], [
            'nombre.required' => 'El nombre descriptivo del documento es obligatorio.',
            'archivo.required' => 'Debes adjuntar un archivo para poder subirlo.',
            'archivo.max' => 'El tamaño máximo permitido para subir archivos es de 10 Megabytes.',
        ]);

        $expedienteId = $expedient->id;
        $archivo = $request->file('archivo');

        // Guardar el archivo físicamente en el storage privado de forma segura
        $ruta = $archivo->store("expedientes/{$expedienteId}");

        // Crear registro en base de datos
        $document = ExpedientDocument::create([
            'expedient_id' => $expedienteId,
            'nombre' => $request->input('nombre'),
            'ruta' => $ruta,
            'tipo_mime' => $archivo->getClientMimeType(),
            'subido_por' => Auth::id(),
            'visibilidad' => $request->input('visibilidad'),
        ]);

        app(\App\Services\DocumentClassificationService::class)->classify($document);

        return back()->with('success', 'Documento subido e indexado correctamente en el expediente.');
    }

    /**
     * Descarga de forma segura un documento.
     */
    public function download($id)
    {
        $documento = ExpedientDocument::findOrFail($id);

        if (!Storage::exists($documento->ruta)) {
            return back()->with('error', 'El archivo físico no se encuentra en el servidor.');
        }

        return Storage::download($documento->ruta, $documento->nombre);
    }

    /**
     * Elimina el registro del documento y borra el archivo del disco.
     */
    public function destroy($id)
    {
        $documento = ExpedientDocument::findOrFail($id);
        
        // Eliminar del almacenamiento físico
        if (Storage::exists($documento->ruta)) {
            Storage::delete($documento->ruta);
        }

        // Eliminar registro (soporta soft delete)
        $documento->delete();

        return back()->with('success', 'Documento eliminado y borrado del servidor con éxito.');
    }

    /**
     * Actualiza la categoría del documento y marca confianza como alta (corrección manual).
     */
    public function updateCategory(Request $request, $id)
    {
        $documento = ExpedientDocument::findOrFail($id);

        $request->validate([
            'categoria' => 'required|string|in:nomina,factura,contrato,notificacion_hacienda,notificacion_seguridad_social,declaracion_fiscal,escritura,dni_nie,justificante_pago,correspondencia,otro',
        ]);

        $documento->update([
            'categoria' => $request->input('categoria'),
            'categoria_confianza' => 'alta',
        ]);

        return response()->json(['success' => true]);
    }
}
