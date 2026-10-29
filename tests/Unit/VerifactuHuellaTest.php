<?php

namespace Tests\Unit;

use App\Support\Verifactu\Huella;
use PHPUnit\Framework\TestCase;

/** Los tres ejemplos del documento oficial de la AEAT (huella v0.1.2, apartado 6). */
class VerifactuHuellaTest extends TestCase
{
    private const PRIMERA = '3C464DAF61ACB827C65FDA19F352A4E3BDC2C640E9E9FC4CC058073F38F12F60';
    private const SEGUNDA = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';

    public function test_caso_1_primer_registro_de_alta(): void
    {
        $huella = Huella::alta([
            'emisor' => '89890001K', 'numSerie' => '12345678/G33', 'fecha' => '01-01-2024', 'tipoFactura' => 'F1',
            'cuotaTotal' => '12.35', 'importeTotal' => '123.45', 'huellaAnterior' => null, 'fechaHora' => '2024-01-01T19:20:30+01:00',
        ]);

        $this->assertSame(self::PRIMERA, $huella);
    }

    public function test_caso_2_alta_encadenada_con_el_registro_anterior(): void
    {
        $huella = Huella::alta([
            'emisor' => '89890001K', 'numSerie' => '12345679/G34', 'fecha' => '01-01-2024', 'tipoFactura' => 'F1',
            'cuotaTotal' => '12.35', 'importeTotal' => '123.45', 'huellaAnterior' => self::PRIMERA, 'fechaHora' => '2024-01-01T19:20:35+01:00',
        ]);

        $this->assertSame(self::SEGUNDA, $huella);
    }

    public function test_caso_3_anulacion(): void
    {
        $huella = Huella::anulacion([
            'emisor' => '89890001K', 'numSerie' => '12345679/G34', 'fecha' => '01-01-2024',
            'huellaAnterior' => self::SEGUNDA, 'fechaHora' => '2024-01-01T19:20:40+01:00',
        ]);

        $this->assertSame('177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68', $huella);
    }

    public function test_los_espacios_al_inicio_y_al_final_de_cada_valor_no_cuentan(): void
    {
        $this->assertSame(
            Huella::calcular('NumSerieFactura=12345678 / G33'),
            Huella::calcular(Huella::cadena(['NumSerieFactura' => '    12345678 / G33  '])),
        );
    }

    public function test_un_campo_vacio_deja_solo_el_nombre_y_el_igual(): void
    {
        $this->assertSame('ImporteTotal=123.45&Huella=&FechaHoraHusoGenRegistro=x', Huella::cadena(['ImporteTotal' => '123.45', 'Huella' => '', 'FechaHoraHusoGenRegistro' => 'x']));
    }
}
