<?php

namespace Tests\Unit;

use App\Support\Verifactu\QrTributario;
use Tests\TestCase;

class VerifactuQrTest extends TestCase
{
    public function test_url_de_pruebas_igual_a_la_del_documento_oficial(): void
    {
        config(['verifactu.entorno' => 'pruebas']);

        $this->assertSame(
            'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=89890001K&numserie=12345678-G33&fecha=01-09-2024&importe=241.4',
            QrTributario::url('89890001K', '12345678-G33', '01-09-2024', '241.4'),
        );
    }

    public function test_url_de_produccion(): void
    {
        config(['verifactu.entorno' => 'produccion']);

        $this->assertStringStartsWith('https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=', QrTributario::url('89890001K', 'A-1', '01-09-2024', '10.00'));
    }

    public function test_los_caracteres_especiales_del_numero_se_codifican(): void
    {
        $this->assertStringContainsString('numserie=12345678%26G33', QrTributario::url('89890001K', '12345678&G33', '01-01-2024', '241.4'));
        $this->assertStringContainsString('numserie=A%2F2026%20%2F%200001', QrTributario::url('89890001K', 'A/2026 / 0001', '01-01-2024', '1.00'));
    }

    public function test_el_qr_es_una_imagen_png_que_se_lee_de_vuelta_con_la_misma_url(): void
    {
        $url = QrTributario::url('89890001K', 'F2026-0001', '05-10-2026', '121.00');

        $uri = QrTributario::dataUri($url);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertSame($url, (string) (new \chillerlan\QRCode\QRCode())->readFromBlob(base64_decode(substr($uri, 22))));
    }
}
