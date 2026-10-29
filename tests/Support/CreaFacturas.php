<?php

namespace Tests\Support;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Support\Verifactu\RegistroXml;
use DOMDocument;

/** Datos inventados para probar Veri*Factu (NIF válidos de ejemplo; nada real). */
trait CreaFacturas
{
    protected function datosDelDespacho(): void
    {
        Setting::setValue('company_name', 'Despacho de Prueba S.L.');
        Setting::setValue('company_nif', 'B12345674');
        Setting::setValue('company_address', 'Calle Falsa 1, 39001 Santander');
    }

    protected function clienteDePrueba(string $nif = '89890001K', string $nombre = 'Cliente de Prueba'): Client
    {
        return Client::create(['codigo' => 'C'.random_int(1000, 9999), 'razon_social' => $nombre, 'nif' => $nif, 'tipo' => 'empresa', 'direccion' => 'Calle Inventada 2, Madrid']);
    }

    /** @param array<int, array{0:string,1:int,2:float,3?:float,4?:string}> $lineas concepto, cantidad, precio, tipo IVA, calificación */
    protected function borrador(Client $cliente, array $lineas = [['Asesoramiento', 1, 100.0]], array $extra = []): Invoice
    {
        $f = Invoice::create($extra + [
            'client_id' => $cliente->id, 'numero' => 'BORRADOR-'.bin2hex(random_bytes(4)), 'fecha' => '2026-10-06',
            'estado' => 'pendiente', 'emision' => Invoice::BORRADOR,
        ]);
        foreach ($lineas as $l) {
            $f->lines()->create(['concepto' => $l[0], 'cantidad' => $l[1], 'precio_unitario' => $l[2], 'subtotal' => $l[1] * $l[2], 'tipo_iva' => $l[3] ?? 21, 'calificacion' => $l[4] ?? 'S1']);
        }

        return $f->fresh();
    }

    /** Valida registros (alta o anulación) metidos en un lote contra los esquemas oficiales de la AEAT. @return array<int, string> errores */
    protected function erroresXsd(string ...$registros): array
    {
        $doc = new DOMDocument();
        $doc->loadXML(RegistroXml::lote('B12345674', 'Despacho de Prueba S.L.', $registros));
        $previo = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc->schemaValidate(resource_path('verifactu/xsd/SuministroLR.xsd'));
        $errores = array_map(fn ($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return $errores;
    }
}
