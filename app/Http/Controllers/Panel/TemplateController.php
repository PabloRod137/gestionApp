<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CaseActivity;
use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Service;
use App\Support\GeneradorDocumentos;
use App\Support\Pdf\PdfException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Plantillas Word: subida (administrador), asignacion a servicios y generacion desde un expediente. */
class TemplateController extends Controller
{
    public function subir(Request $request, DocumentTemplate $template, GeneradorDocumentos $generador)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120', 'mimes:docx'],
        ], [
            'archivo.mimes' => 'Sube un documento Word (.docx).',
            'archivo.max' => 'El archivo no puede superar los 5 MB.',
        ]);

        $ruta = $request->file('archivo')->storeAs('plantillas', $template->codigo.'.docx');
        $template->update(['archivo' => $ruta]);

        try {
            $campos = $generador->camposDe($ruta);
        } catch (\Throwable) {
            Storage::delete($ruta);
            $template->update(['archivo' => null]);

            return back()->withErrors(['archivo' => 'No se ha podido leer el documento. Comprueba que es un Word válido.']);
        }

        return back()->with('success', 'Plantilla guardada. Campos detectados: '.(count($campos) ?: 'ninguno').'.');
    }

    public function asignar(Request $request, Service $service)
    {
        $data = $request->validate(['document_template_id' => ['required', 'exists:document_templates,id']]);
        $service->templates()->syncWithoutDetaching([$data['document_template_id']]);

        return back()->with('success', 'Plantilla asignada al servicio.');
    }

    public function quitar(Service $service, DocumentTemplate $template)
    {
        $service->templates()->detach($template->id);

        return back()->with('success', 'Plantilla quitada del servicio.');
    }

    public function generar(Request $request, Expedient $expedient, GeneradorDocumentos $generador)
    {
        $data = $request->validate(['document_template_id' => ['required', 'exists:document_templates,id']]);

        $plantilla = DocumentTemplate::findOrFail($data['document_template_id']);
        if (! $plantilla->archivo || ! Storage::exists($plantilla->archivo)) {
            return back()->withErrors(['document_template_id' => 'Esta plantilla todavía no tiene archivo Word.']);
        }

        // lo que falta (importe, firmante, un teléfono...) lo escribe la persona en el momento o se deja en blanco; el programa no lo inventa
        $extras = array_map(fn ($v) => trim((string) $v), (array) $request->input('extra', []));
        $pend = $generador->pendientes($plantilla, $expedient, $extras);
        if ($pend['pedir'] || (! $request->boolean('forzar') && $pend['faltan'])) {
            return back()->with('pedir', $pend['pedir'])->with('faltan', $pend['faltan'])->with('extras', $extras)->with('plantilla_id', $plantilla->id);
        }

        if ($request->boolean('guardar_ficha') && ($campos = $generador->guardarEnFicha($expedient, $extras, $request->user()))) {
            CaseActivity::registrar($expedient, 'DATOS_FICHA_COMPLETADOS', 'Completados en el momento: '.implode(', ', $campos).'.', $request->user());
        }
        $word = $generador->generar($plantilla, $expedient, $request->user(), $extras);
        try {
            $generador->pdfDe($word, $request->user());
        } catch (PdfException) {
            return back()->with('success', 'Documento Word generado y guardado en el expediente, pero no se pudo crear el PDF automáticamente.');
        }

        return back()->with('success', 'Documento generado y guardado en el expediente (Word y PDF).');
    }
}
