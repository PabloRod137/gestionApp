<?php

namespace App\Support\Comunidades;

use App\Models\Appointment;
use App\Models\ExpedientDocument;
use App\Models\Community;
use App\Models\Expedient;
use App\Models\Proposal;
use App\Models\ReporteMensual as Reporte;
use App\Models\User;
use App\Support\Workflow\ProposalWorkflow;
use Illuminate\Support\Carbon;

/**
 * Reporte mensual de las comunidades. El documento Word (modelo `WV_COM_REPORTE_MENSUAL_V1_OBS`) lleva las cifras del
 * mes y dos textos. El programa guarda lo que el equipo confirma para cada comunidad y mes, propone el texto (sin IA, a
 * partir de lo registrado) y rellena el modelo; lo que no esté guardado se pide al generar el documento.
 */
final class ReporteMensual
{
    public const MODELO = 'WV_COM_REPORTE_MENSUAL_V1_OBS';

    /** Marcas del modelo que salen de este reporte (se añaden a los campos que conoce el generador). */
    public const CAMPOS = [
        'periodo' => 'Periodo del reporte (mes y año)',
        'ingresos' => 'Ingresos del periodo',
        'gastos' => 'Gastos del periodo',
        'saldo' => 'Saldo a cierre del periodo',
        'cuotas_pendientes' => 'Cuotas pendientes de cobro',
        'incidencias' => 'Incidencias y actuaciones realizadas',
        'proximas_actuaciones' => 'Próximas actuaciones',
    ];

    /** El reporte que se prepara hoy es el del mes anterior. */
    public static function periodoPorDefecto(?Carbon $hoy = null): string
    {
        return ($hoy ?? now())->copy()->startOfMonth()->subMonth()->format('Y-m');
    }

    public static function etiqueta(string $periodo): string
    {
        return Carbon::createFromFormat('Y-m-d', $periodo.'-01')->locale('es')->isoFormat('MMMM [de] YYYY');
    }

    public static function periodoValido(?string $periodo): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $periodo);
    }

    /** Últimos meses para elegir en el formulario. @return array<string,string> periodo => etiqueta */
    public static function periodosElegibles(int $meses = 14): array
    {
        $r = [];
        for ($i = 1; $i <= $meses; $i++) {
            $p = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $r[$p] = self::etiqueta($p);
        }

        return $r;
    }

    /** «1.234,56», «1234,56» o «1234.56» → número; vacío o ilegible → null. */
    public static function numero(?string $texto): ?float
    {
        $t = trim(str_replace(['€', ' ', "\u{a0}"], '', (string) $texto));
        if ($t === '' || ! preg_match('/^-?[\d.,]+$/', $t)) {
            return null;
        }
        if (str_contains($t, ',')) {
            $t = str_replace(',', '.', str_replace('.', '', $t));
        } elseif (substr_count($t, '.') > 1) {
            $t = str_replace('.', '', $t);
        }

        return is_numeric($t) ? round((float) $t, 2) : null;
    }

    public static function moneda(?float $n): ?string
    {
        return $n === null ? null : number_format($n, 2, ',', '.');
    }

    public static function comunidadDe(Expedient $e): ?Community
    {
        return Community::where('client_id', $e->client_id)->first();
    }

    /**
     * Valores para las marcas del modelo (todos null si no hay comunidad o aún no hay reporte guardado para el periodo).
     *
     * @return array<string, ?string>
     */
    public static function valores(Expedient $e, ?string $periodo = null): array
    {
        $vacios = array_fill_keys(array_keys(self::CAMPOS), null);
        $c = self::comunidadDe($e);
        if (! $c) {
            return $vacios;
        }
        $periodo ??= self::periodoPorDefecto();
        $r = Reporte::where('community_id', $c->id)->where('periodo', $periodo)->first();
        if (! $r) {
            return ['periodo' => self::etiqueta($periodo)] + $vacios;
        }
        $texto = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        return [
            'periodo' => self::etiqueta($periodo),
            'ingresos' => self::moneda($r->ingresos === null ? null : (float) $r->ingresos),
            'gastos' => self::moneda($r->gastos === null ? null : (float) $r->gastos),
            'saldo' => self::moneda($r->saldo === null ? null : (float) $r->saldo),
            'cuotas_pendientes' => self::moneda($r->cuotas_pendientes === null ? null : (float) $r->cuotas_pendientes),
            'incidencias' => $texto($r->incidencias),
            'proximas_actuaciones' => $texto($r->proximas_actuaciones),
        ];
    }

    /**
     * Textos que el programa propone para el formulario (la persona los revisa y los guarda). Sin IA: solo lo registrado (documentos, citas y propuestas en curso).
     *
     * @return array{incidencias: string, proximas_actuaciones: string, saldo_previsto: ?float}
     */
    public static function sugerencias(Community $c, string $periodo): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $periodo.'-01')->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $expedientes = Expedient::where('client_id', $c->client_id)->pluck('id');

        // Lo registrado en el mes: documentos del expediente (sin las copias PDF automáticas) y citas realizadas
        $incidencias = [];
        foreach (ExpedientDocument::whereIn('expedient_id', $expedientes)->whereBetween('created_at', [$inicio, $fin])
            ->where(fn ($q) => $q->whereNull('origen')->orWhere('origen', '!=', 'PDF_AUTOMATICO'))->orderBy('created_at')->take(15)->get() as $d) {
            $incidencias[] = '• '.$d->created_at->format('d/m/Y').': documento «'.trim($d->nombre).'»';
        }
        foreach (Appointment::where('client_id', $c->client_id)->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->where('estado', 'realizada')->orderBy('fecha')->get() as $cita) {
            $incidencias[] = '• '.Carbon::parse($cita->fecha)->format('d/m/Y').': cita realizada'.($cita->notas ? ' — '.trim($cita->notas) : '');
        }

        $proximas = [];
        foreach (Appointment::where('client_id', $c->client_id)->whereDate('fecha', '>', $fin)->whereIn('estado', ['pendiente', 'confirmada'])->orderBy('fecha')->take(5)->get() as $cita) {
            $proximas[] = '• '.Carbon::parse($cita->fecha)->format('d/m/Y').': cita'.($cita->notas ? ' — '.trim($cita->notas) : '');
        }
        foreach (Proposal::whereIn('expedient_id', $expedientes)->whereIn('status', ['proposed', 'approved', 'generated', 'document_approved'])->latest()->take(5)->get() as $p) {
            $proximas[] = '• '.trim($p->title);
        }

        $anterior = Reporte::where('community_id', $c->id)->where('periodo', '<', $periodo)->orderByDesc('periodo')->first();

        return [
            'incidencias' => implode("\n", $incidencias),
            'proximas_actuaciones' => implode("\n", $proximas),
            'saldo_previsto' => $anterior?->saldo !== null ? (float) $anterior->saldo : null,
        ];
    }

    /** Expediente de administración de la comunidad sobre el que se prepara el reporte. */
    public static function expedienteDe(Community $c): ?Expedient
    {
        return Expedient::where('client_id', $c->client_id)
            ->whereHas('service', fn ($q) => $q->where('codigo', 'like', 'ADM_COM%'))
            ->whereNotIn('estado', ['finalizado', 'cancelado'])->latest('id')->first();
    }

    /** Crea la propuesta del reporte (pendiente de aprobar). @return Proposal|null null si no hay expediente de administración o modelo */
    public static function preparar(Community $c, string $periodo, User $por, ProposalWorkflow $flujo): ?Proposal
    {
        $e = self::expedienteDe($c);
        $modelo = \App\Models\DocumentTemplate::where('codigo', self::MODELO)->where('activo', true)->first();
        if (! $e || ! $modelo) {
            return null;
        }

        return $flujo->crear($e, 'Reporte mensual '.self::etiqueta($periodo), 'Reporte de gestión de la comunidad correspondiente a '.self::etiqueta($periodo).'.', $modelo, $por);
    }
}
