<?php

namespace Tests\Unit;

use App\Support\Verifactu\NifEspanol;
use PHPUnit\Framework\TestCase;

class NifEspanolTest extends TestCase
{
    public function test_dni_nie_y_cif_validos(): void
    {
        foreach (['89890001K', '12345678Z', 'X1234567L', 'Y1234567X', 'A58818501', 'B12345674', 'Q2826000H', ' 12345678-z '] as $nif) {
            $this->assertTrue(NifEspanol::valido($nif), $nif);
        }
    }

    public function test_letra_o_digito_de_control_incorrecto(): void
    {
        foreach (['12345678A', '89890001L', 'X1234567A', 'A58818502', 'B12345675', '', '1234', 'ABCDEFGHI', null] as $nif) {
            $this->assertFalse(NifEspanol::valido($nif), (string) $nif);
        }
    }
}
