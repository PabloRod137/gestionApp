<?php

namespace App\Observers;

use App\Models\Expedient;
use App\Support\Cobros\PendienteDeFacturar;
use App\Support\Cobros\PrepararFactura;

/**
 * Evento «el expediente cambia» → si ahora cumple la regla de facturación, se prepara el borrador (nunca se emite).
 * Solo reacciona a crear el expediente y a cambios de estado o de servicio, y no a cierres antiguos: así una importación o
 * sincronización masiva no llena el panel de borradores de expedientes viejos.
 */
class FacturaDesdeExpedienteObserver
{
    /** Un expediente nuevo solo genera borrador si se factura «al abrir»; uno que llega ya finalizado (importación) es historia. */
    public function created(Expedient $e): void
    {
        if ($e->estado !== 'finalizado') {
            $this->preparar($e);
        }
    }

    public function updated(Expedient $e): void
    {
        if (! $e->wasChanged(['estado', 'service_id'])) {
            return;
        }
        // Un cierre antiguo que reaparece al sincronizar no vuelve a pedir factura: solo cierres recientes
        if ($e->estado === 'finalizado' && $e->fecha_cierre && $e->fecha_cierre->lt(now()->subDays(45))) {
            return;
        }
        $this->preparar($e);
    }

    private function preparar(Expedient $e): void
    {
        if (! config('facturacion.borradores_automaticos') || ! $e->service_id) {
            return;
        }
        if (PendienteDeFacturar::evaluar($e)['estado'] === PendienteDeFacturar::LISTO) {
            PrepararFactura::desdeExpediente($e);
        }
    }
}
