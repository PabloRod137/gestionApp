<?php

namespace App\Support;

use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Workflow\ProposalWorkflow;
use Illuminate\Support\Facades\Storage;

/**
 * Propuestas automáticas por reglas (sin IA): del servicio del expediente salen los pasos habituales
 * (presupuesto, documento y factura). Solo deja propuestas pendientes de aprobar: no genera ni envía nada.
 */
class PlanActuaciones
{
    public function __construct(private ProposalWorkflow $flujo) {}

    /** @return array<int, array{tipo: string, titulo: string, finalidad: string, modelo: ?DocumentTemplate, estado: string, nota: ?string}> */
    public function pasos(Expedient $e): array
    {
        $e->loadMissing('service.templates');
        $s = $e->service;
        if (! $s) {
            return [];
        }
        $cobraSeparado = $s->facturable && ! in_array($s->tipo_servicio, ['Incluido en cuota', 'No facturable'], true);
        $importe = $this->importe($s);
        $pasos = [];

        if ($cobraSeparado) {
            $presupuesto = DocumentTemplate::where('codigo', config('recetas.modelo_presupuesto'))->first();
            $pasos[] = $this->paso($e, 'presupuesto', "Presupuesto: {$s->nombre}", "Presupuestar «{$s->nombre}» al cliente. {$importe}".($s->notas ? " Condiciones: {$s->notas}" : ''), $presupuesto);
        }
        if ($s->genera_documento) {
            $modelos = $s->templates->where('activo', true)->reject(fn ($t) => $t->codigo === config('recetas.modelo_presupuesto'));
            if ($modelos->isEmpty()) {
                $pasos[] = ['tipo' => 'documento', 'titulo' => 'Documento del servicio', 'finalidad' => '', 'modelo' => null, 'estado' => 'sin_modelo', 'nota' => 'Este servicio genera documento pero no tiene un modelo asignado.'];
            }
            foreach ($modelos as $m) {
                $pasos[] = $this->paso($e, 'documento', "{$m->nombre}: {$e->titulo}", "Preparar «{$m->nombre}» para el expediente {$e->codigo} ({$s->nombre}).", $m);
            }
        }
        if ($cobraSeparado) {
            $pasos[] = ['tipo' => 'factura', 'titulo' => "Factura: {$s->nombre}", 'finalidad' => $importe, 'modelo' => null, 'estado' => 'manual', 'nota' => 'La factura se prepara en Facturación, con los importes ya rellenados.'];
        }

        return $pasos;
    }

    /** Crea una propuesta (pendiente de aprobar) por cada paso con modelo que aún no la tenga. @return int propuestas creadas */
    public function crear(Expedient $e, User $por): int
    {
        $n = 0;
        foreach ($this->pasos($e) as $p) {
            if ($p['estado'] !== 'pendiente') {
                continue;
            }
            $this->flujo->crear($e, mb_substr($p['titulo'], 0, 160), mb_substr($p['finalidad'], 0, 3000), $p['modelo'], $por);
            $n++;
        }

        return $n;
    }

    private function paso(Expedient $e, string $tipo, string $titulo, string $finalidad, ?DocumentTemplate $modelo): array
    {
        if (! $modelo || ! $modelo->archivo || ! Storage::exists($modelo->archivo)) {
            return ['tipo' => $tipo, 'titulo' => $titulo, 'finalidad' => $finalidad, 'modelo' => null, 'estado' => 'sin_modelo', 'nota' => 'El modelo todavía no tiene archivo Word.'];
        }
        $existe = Proposal::where('expedient_id', $e->id)->where('document_template_id', $modelo->id)->where('status', '!=', 'rejected')->exists();

        return ['tipo' => $tipo, 'titulo' => $titulo, 'finalidad' => $finalidad, 'modelo' => $modelo, 'estado' => $existe ? 'propuesta' : 'pendiente', 'nota' => null];
    }

    private function importe(\App\Models\Service $s): string
    {
        $base = (float) $s->precio_base;
        if ($base <= 0) {
            return 'Importe a definir.';
        }
        $iva = (float) ($s->iva ?? 21);
        $f = fn (float $v) => number_format($v, 2, ',', '.');

        return 'Importe: '.$f($base).' € + '.rtrim(rtrim(number_format($iva, 2, ',', ''), '0'), ',').' % IVA = '.$f($base * (1 + $iva / 100)).' €.';
    }
}
