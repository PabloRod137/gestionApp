<?php

namespace App\Imports;

use App\Models\Expedient;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Clase de importación de expedientes desde Excel.
 */
class ExpedientsImport implements ToModel, WithHeadingRow
{
    /**
     * Mapea cada fila de la cabecera del Excel para crear un expediente.
     */
    public function model(array $row)
    {
        $titulo = trim($row['titulo'] ?? $row['asunto'] ?? '');
        $clientId = $row['client_id'] ?? $row['cliente_id'] ?? null;
        $workerId = $row['worker_id'] ?? $row['asesor_id'] ?? null;
        $serviceId = $row['service_id'] ?? $row['servicio_id'] ?? null;

        if (empty($titulo) || empty($clientId)) {
            return null;
        }

        // Obtener estado válido
        $estado = strtolower(trim($row['estado'] ?? 'abierto'));
        $estadosValidos = ['abierto', 'en_proceso', 'pendiente_cliente', 'finalizado', 'cancelado'];
        if (!in_array($estado, $estadosValidos)) {
            $estado = 'abierto';
        }

        // Procesar fecha apertura
        $fechaApertura = $row['fecha_apertura'] ?? null;
        if (!$fechaApertura) {
            $fechaApertura = now()->format('Y-m-d');
        }

        return new Expedient([
            'titulo'         => $titulo,
            'client_id'      => intval($clientId),
            'worker_id'      => $workerId ? intval($workerId) : null,
            'service_id'     => $serviceId ? intval($serviceId) : null,
            'estado'         => $estado,
            'fecha_apertura' => $fechaApertura,
            'descripcion'    => $row['descripcion'] ?? $row['detalles'] ?? null,
        ]);
    }
}
