<?php

namespace App\Imports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Clase de importación de clientes desde Excel.
 */
class ClientsImport implements ToModel, WithHeadingRow
{
    /**
     * Mapea cada fila de la cabecera del Excel para crear un modelo de cliente.
     */
    public function model(array $row)
    {
        // Limpiar NIF y Razón Social de espacios y comprobar obligatorios
        $nif = trim($row['nif'] ?? $row['nif_cif'] ?? '');
        $razonSocial = trim($row['razon_social'] ?? $row['nombre'] ?? '');

        if (empty($nif) || empty($razonSocial)) {
            return null;
        }

        // Si ya existe un cliente con ese NIF, evitar duplicar
        if (Client::where('nif', $nif)->exists()) {
            return null;
        }

        return new Client([
            'nif'          => $nif,
            'razon_social' => $razonSocial,
            'tipo'         => strtolower(trim($row['tipo'] ?? 'particular')),
            'telefono'     => $row['telefono'] ?? null,
            'direccion'    => $row['direccion'] ?? null,
            'notas'        => $row['notas'] ?? null,
        ]);
    }
}
