<?php

namespace App\Support\Sync;

use App\Models\CaseActivity;
use App\Models\Client;
use App\Models\Expedient;
use App\Models\SyncEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Convierte los avances del panel en eventos para SharePoint. No escribe en SharePoint: solo prepara y lleva la cuenta.
 * Ciclo de cada evento: pendiente -> exportado (entregado a Power Automate) -> confirmado (SharePoint lo refleja).
 */
class PuenteSharePoint
{
    /** Estado interno -> vocabulario de la lista de SharePoint (null = no existe allí, no se manda). */
    public const ESTADOS = [
        'abierto' => 'No iniciado',
        'en_proceso' => 'En curso',
        'pendiente_cliente' => 'En curso',
        'finalizado' => 'Completado',
        'cancelado' => null,
    ];

    /** Acciones del historial que son avances y salen hacia SharePoint: acción => [Actividad, Tipo_Actividad]. */
    private const AVANCES = [
        'PROPUESTA_CREADA' => ['Propuesta documental creada', 'Propuesta'],
        'PROPUESTA_APROBADA' => ['Propuesta aprobada', 'Propuesta'],
        'PROPUESTA_RECHAZADA' => ['Propuesta rechazada', 'Propuesta'],
        'DOCUMENTO_GENERADO' => ['Documento generado', 'Documento'],
        'PDF_ADJUNTADO' => ['PDF revisado adjuntado', 'Documento'],
        'DOCUMENTO_APROBADO' => ['Documento aprobado', 'Documento'],
        'COMUNICACION_PREPARADA' => ['Comunicación preparada', 'Comunicación'],
        'COMUNICACION_LIBRE' => ['Comunicación preparada', 'Comunicación'],
        'BORRADOR_CREADO' => ['Borrador creado en Outlook', 'Comunicación'],
        'SERVICIO_ASIGNADO' => ['Servicio asignado', 'Expediente'],
    ];

    /** Los primeros avances que sacan al expediente de "No iniciado". */
    private const PRIMEROS_AVANCES = ['PROPUESTA_APROBADA', 'DOCUMENTO_GENERADO'];

    public static function estadoSharePoint(?string $estado): ?string
    {
        return self::ESTADOS[$estado ?? ''] ?? null;
    }

    /** Se llama desde el historial del expediente. Un fallo aquí nunca debe romper el trabajo de la persona. */
    public static function desdeActividad(CaseActivity $actividad): void
    {
        if (! isset(self::AVANCES[$actividad->action])) {
            return;
        }
        try {
            $expediente = Expedient::find($actividad->expedient_id);
            if (! $expediente) {
                return;
            }
            $por = $actividad->user_id ? User::find($actividad->user_id) : null;
            if (in_array($actividad->action, self::PRIMEROS_AVANCES, true)) {
                self::avanzar($expediente, $por);
            }
            [$titulo, $tipo] = self::AVANCES[$actividad->action];
            self::crear($expediente, $tipo, $titulo, $actividad->detail, $por, null, $actividad->created_at);
        } catch (\Throwable $e) {
            Log::warning('PuenteSharePoint: no se pudo registrar el evento: '.$e->getMessage());
        }
    }

    /** El expediente pasó de un estado a otro (lo hizo una persona o el avance automático). */
    public static function cambioDeEstado(Expedient $expediente, string $antes, string $despues, ?User $por = null): void
    {
        if ($antes === $despues) {
            return;
        }
        $expediente->refresh();
        self::crear($expediente, 'Estado', 'Cambio de estado', 'De «'.(self::estadoSharePoint($antes) ?? $antes).'» a «'.(self::estadoSharePoint($despues) ?? $despues).'».', $por, self::estadoSharePoint($antes));
    }

    /** Primer avance: de "abierto" a "en proceso". Nunca toca otros estados ni finaliza. */
    public static function avanzar(Expedient $expediente, ?User $por = null): void
    {
        if (! config('sync.avance_automatico') || $expediente->estado !== 'abierto') {
            return;
        }
        $expediente->update(['estado' => 'en_proceso']);
        self::cambioDeEstado($expediente, 'abierto', 'en_proceso', $por);
    }

    /** El cliente (o el despacho) ha completado datos de la ficha: sale hacia SharePoint con los valores. Un fallo aquí nunca rompe el guardado. */
    public static function datosDeCliente(Client $cliente, array $campos, ?User $por = null): ?SyncEvent
    {
        try {
            $datos = [];
            foreach ($campos as $campo) {
                $datos[$campo] = $cliente->{$campo};
            }
            $evento = SyncEvent::create([
                'client_id' => $cliente->id,
                'tipo' => 'Cliente',
                'actividad' => 'Datos del cliente completados',
                'descripcion' => 'Campos: '.implode(', ', $campos).'.',
                'datos' => $datos,
                'actor' => $por?->name,
                'origen' => 'Nortia',
                'ocurrido_en' => now(),
                'estado' => 'pendiente',
            ]);
            $evento->update(['evento_id' => sprintf('LG-%06d', $evento->id)]);

            return $evento;
        } catch (\Throwable $e) {
            Log::warning('PuenteSharePoint: no se pudo registrar los datos del cliente: '.$e->getMessage());

            return null;
        }
    }

    private static function crear(Expedient $expediente, string $tipo, string $actividad, ?string $descripcion, ?User $por, ?string $estadoAnterior = null, $cuando = null): SyncEvent
    {
        $evento = SyncEvent::create([
            'expedient_id' => $expediente->id,
            'tipo' => $tipo,
            'actividad' => $actividad,
            'descripcion' => $descripcion ? mb_substr($descripcion, 0, 500) : null,
            'actor' => $por?->name,
            'origen' => 'Nortia',
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => self::estadoSharePoint($expediente->estado),
            'ocurrido_en' => $cuando ?? now(),
            'estado' => 'pendiente',
        ]);
        $evento->update(['evento_id' => sprintf('LG-%06d', $evento->id)]);

        return $evento;
    }

    /** @param  Collection<int, SyncEvent>  $eventos solo pasan a "exportado" los que estaban pendientes */
    public static function marcarExportados(Collection $eventos, string $archivo): void
    {
        SyncEvent::whereIn('id', $eventos->pluck('id'))->where('estado', 'pendiente')
            ->update(['estado' => 'exportado', 'exportado_en' => now(), 'archivo' => $archivo]);
    }

    /** SharePoint ya refleja estos eventos. Los identificadores desconocidos se ignoran. @return int cuántos se confirmaron */
    public static function confirmar(array $ids): int
    {
        return SyncEvent::whereIn('evento_id', array_filter($ids, 'is_string'))->where('estado', '!=', 'confirmado')
            ->update(['estado' => 'confirmado', 'confirmado_en' => now()]);
    }
}
