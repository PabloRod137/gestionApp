<?php

namespace App\Support\Documental;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Hallazgo;
use App\Models\HallazgoDecision;
use App\Models\User;
use App\Support\DatosCliente;
use App\Support\Sync\PuenteSharePoint;
use Illuminate\Support\Facades\DB;

/**
 * Decisiones humanas sobre los hallazgos. Un dato del documento nunca pasa a la ficha por sí solo: una persona lo acepta
 * (si era un hueco), lo corrige (si difería de la ficha) o lo rechaza. Cada decisión queda en el historial y la ficha
 * cambia con las mismas reglas de validación que cuando la completa el cliente.
 */
class Bandeja
{
    /** @throws \DomainException con el motivo en lenguaje llano */
    public function decidir(Hallazgo $h, string $accion, User $por, ?string $nota = null): Hallazgo
    {
        if (! in_array($accion, ['aceptar', 'corregir', 'rechazar', 'reabrir'], true)) {
            throw new \DomainException('Acción no válida.');
        }

        return DB::transaction(function () use ($h, $accion, $por, $nota) {
            $h = Hallazgo::lockForUpdate()->findOrFail($h->id);
            $cliente = Client::findOrFail($h->client_id);   // siempre el cliente del documento, nunca otro
            $antes = $cliente->{$h->campo};

            if ($accion === 'reabrir') {
                if (! in_array($h->estado, ['rechazado'], true)) {
                    throw new \DomainException('Solo se reabre un hallazgo rechazado.');
                }
                $h->update(['estado' => 'pendiente', 'decidido_por' => null, 'decidido_at' => null]);
                $this->anotar($h, $accion, $por, $antes, $antes, $nota);

                return $h;
            }
            if ($h->estado !== 'pendiente') {
                throw new \DomainException('Este hallazgo ya está resuelto.');
            }

            if ($accion === 'rechazar') {
                $h->update(['estado' => 'rechazado', 'decidido_por' => $por->id, 'decidido_at' => now()]);
                $this->anotar($h, $accion, $por, $antes, $antes, $nota);

                return $h;
            }

            $valor = DatosCliente::normalizar($h->campo, $h->valor);
            if ($valor === null) {
                throw new \DomainException('El dato propuesto no tiene un formato válido.');
            }
            if ($h->campo === 'nif' && Client::withTrashed()->where('nif', $valor)->where('id', '!=', $cliente->id)->exists()) {
                throw new \DomainException('Ese NIF ya consta en otra ficha: no se guarda. Rechaza el hallazgo o revisa las fichas.');
            }

            if ($accion === 'aceptar') {
                if (filled($antes)) {
                    throw new \DomainException('La ficha ya tiene este dato. Si el del documento es el correcto, usa «Corregir».');
                }
                $cliente->{$h->campo} = $valor;
                $estado = 'aceptado';
            } else { // corregir
                if (blank($antes)) {
                    throw new \DomainException('La ficha está vacía en este campo: usa «Aceptar».');
                }
                $cliente->{$h->campo} = $valor;
                $estado = 'corregido';
            }
            $cliente->save();

            AuditLog::create([
                'user_id' => $por->id, 'accion' => 'hallazgo_'.$estado, 'modelo' => 'Client', 'modelo_id' => $cliente->id,
                'datos_nuevos' => ['campo' => $h->campo, 'hallazgo' => $h->id], 'ip' => request()?->ip(),
            ]);
            PuenteSharePoint::datosDeCliente($cliente, [$h->campo], $por);

            $h->update(['estado' => $estado, 'decidido_por' => $por->id, 'decidido_at' => now()]);
            $this->anotar($h, $accion, $por, $antes, $valor, $nota);

            return $h;
        });
    }

    private function anotar(Hallazgo $h, string $accion, User $por, ?string $antes, ?string $despues, ?string $nota): void
    {
        HallazgoDecision::create([
            'hallazgo_id' => $h->id, 'user_id' => $por->id, 'accion' => $accion,
            'valor_antes' => $antes, 'valor_despues' => $despues, 'nota' => $nota ? mb_substr($nota, 0, 300) : null,
        ]);
    }
}
