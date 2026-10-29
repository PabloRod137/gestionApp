<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CaseActivity;
use App\Models\Expedient;
use App\Support\Ia\AnalisisExpediente;
use App\Support\Ia\IaException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Razonar con IA (modelo local). Propone; no escribe nada salvo cuando una persona confirma un dato concreto.
 */
class IaController extends Controller
{
    public function razonar(Request $request, Expedient $expedient, AnalisisExpediente $analisis)
    {
        return $this->analizar($request, $expedient, $analisis, back());
    }

    /** Desde Mi jornada: se indica el expediente (por su código) y se vuelve a la portada. */
    public function preguntar(Request $request, AnalisisExpediente $analisis)
    {
        $codigo = trim((string) $request->input('expediente'));
        $expedient = $codigo === '' ? null : Expedient::where('codigo', $codigo)->first();
        if (! $expedient) {
            return redirect()->route('panel.dashboard')->withErrors(['expediente' => 'No existe ningún expediente con ese código. Elige uno de la lista.'])->withInput();
        }

        return $this->analizar($request, $expedient, $analisis, redirect()->route('panel.dashboard'));
    }

    private function analizar(Request $request, Expedient $expedient, AnalisisExpediente $analisis, $destino)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:1200']], ['question.required' => 'Indica una duda concreta.', 'question.max' => 'La duda no puede superar los 1.200 caracteres.']);

        try {
            $r = $analisis->analizar($expedient, $data['question']);
        } catch (IaException $e) {
            return $destino->withErrors(['ia' => $e->getMessage()])->withInput();
        }
        // se deja constancia de que se usó, sin guardar la pregunta ni la respuesta
        CaseActivity::registrar($expedient, 'RAZONAMIENTO_IA', "Ruta {$r['ruta']}, modelo {$r['modelo']}; pregunta de ".mb_strlen($data['question']).' caracteres.', $request->user());

        return $destino->with('ia_resultado', $r + ['expediente_id' => $expedient->id, 'codigo' => $expedient->codigo, 'cliente' => $expedient->client->razon_social]);
    }

    /** Incorpora a la ficha del cliente un dato que una persona ha comprobado. */
    public function incorporar(Request $request, Expedient $expedient)
    {
        $data = $request->validate([
            'campo' => ['required', Rule::in(['nif', 'email', 'telefono', 'direccion'])],
            'valor' => ['required', 'string', 'max:255'],
            'fuente' => ['nullable', 'string', 'max:255'],
            'confirmado' => ['accepted'],
        ], ['confirmado.accepted' => 'Confirma que has comprobado la fuente del dato.', 'campo.in' => 'Ese campo no se puede completar desde aquí.']);

        $campo = $data['campo'];
        $valor = trim($data['valor']);
        $error = match ($campo) {
            'nif' => preg_match('/^([0-9XYZ]\d{7}[A-Z]|[A-HJNPQRSUVW]\d{7}[0-9A-J])$/', $valor = strtoupper(str_replace([' ', '-'], '', $valor))) ? null : 'El NIF/CIF no tiene un formato válido.',
            'email' => filter_var($valor, FILTER_VALIDATE_EMAIL) ? null : 'El correo no es válido.',
            'telefono' => preg_match('/^\+?[0-9 ]{9,15}$/', $valor) ? null : 'El teléfono no es válido.',
            default => mb_strlen($valor) >= 5 ? null : 'La dirección es demasiado corta.',
        };
        if ($error) {
            return back()->withErrors(['valor' => $error])->withInput();
        }

        $cliente = $expedient->client;
        if (filled($cliente->{$campo}) && ! $request->boolean('reemplazar')) {
            return back()->withErrors(['valor' => 'El cliente ya tiene ese dato. Marca "Reemplazar el dato actual" si quieres cambiarlo.'])->withInput();
        }
        $cliente->update([$campo => $valor]);
        CaseActivity::registrar($expedient, 'DATO_INCORPORADO_IA', "Campo {$campo} completado a partir de una propuesta; fuente: ".($data['fuente'] ?? null ?: 'no indicada').'.', $request->user());

        return back()->with('success', 'Dato incorporado a la ficha del cliente.');
    }
}
