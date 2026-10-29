<?php

namespace Tests\Feature;

use App\Support\Pdf\ConversorPdf;
use App\Support\Pdf\ConversorPdfAutomatico;
use App\Support\Pdf\PdfException;
use App\Support\Pdf\WordConversor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/** Selector del motor de PDF: Microsoft Word si está instalado (mejor fidelidad), LibreOffice como respaldo. */
class ConversorPdfAutomaticoTest extends TestCase
{
    private function motor(string $nombre, bool $disponible, ?string $resultado = null, bool $falla = false, ?array &$llamadas = null): ConversorPdf
    {
        $llamadas ??= [];

        return new class($nombre, $disponible, $resultado, $falla, $llamadas) implements ConversorPdf
        {
            public function __construct(private string $nombre, private bool $ok, private ?string $resultado, private bool $falla, private array &$llamadas) {}

            public function nombre(): string
            {
                return $this->nombre;
            }

            public function disponible(): bool
            {
                return $this->ok;
            }

            public function convertir(string $docx, string $nombre = 'documento'): string
            {
                $this->llamadas[] = $this->nombre;
                if ($this->falla) {
                    throw new PdfException("{$this->nombre} falló");
                }

                return $this->resultado ?? "%PDF-1.4\n{$this->nombre}\n%%EOF\n";
            }
        };
    }

    public function test_usa_word_si_esta_disponible_y_no_toca_libreoffice(): void
    {
        $llamadas = [];
        $auto = new ConversorPdfAutomatico([$this->motor('word', true, null, false, $llamadas), $this->motor('libreoffice', true, null, false, $llamadas)]);

        $pdf = $auto->convertir('x');

        $this->assertStringContainsString('word', $pdf);
        $this->assertSame(['word'], $llamadas);
    }

    public function test_si_word_no_esta_instalado_usa_libreoffice(): void
    {
        $llamadas = [];
        $auto = new ConversorPdfAutomatico([$this->motor('word', false, null, false, $llamadas), $this->motor('libreoffice', true, null, false, $llamadas)]);

        $this->assertStringContainsString('libreoffice', $auto->convertir('x'));
        $this->assertSame(['libreoffice'], $llamadas);
    }

    public function test_si_word_falla_al_convertir_se_prueba_con_libreoffice(): void
    {
        $llamadas = [];
        $auto = new ConversorPdfAutomatico([$this->motor('word', true, null, true, $llamadas), $this->motor('libreoffice', true, null, false, $llamadas)]);

        $this->assertStringContainsString('libreoffice', $auto->convertir('x'));
        $this->assertSame(['word', 'libreoffice'], $llamadas);
    }

    public function test_si_ninguno_esta_disponible_el_error_dice_que_instalar(): void
    {
        $auto = new ConversorPdfAutomatico([$this->motor('word', false), $this->motor('libreoffice', false)]);

        $this->expectException(PdfException::class);
        $this->expectExceptionMessage('Microsoft Word');
        $auto->convertir('x');
    }

    public function test_si_todos_fallan_el_error_recoge_el_motivo_de_cada_uno(): void
    {
        $auto = new ConversorPdfAutomatico([$this->motor('word', true, null, true), $this->motor('libreoffice', true, null, true)]);

        try {
            $auto->convertir('x');
            $this->fail('Debía fallar');
        } catch (PdfException $e) {
            $this->assertStringContainsString('word falló', $e->getMessage());
            $this->assertStringContainsString('libreoffice falló', $e->getMessage());
        }
    }

    public function test_un_motor_que_devuelve_algo_que_no_es_un_pdf_se_descarta(): void
    {
        $llamadas = [];
        $auto = new ConversorPdfAutomatico([$this->motor('word', true, 'basura', false, $llamadas), $this->motor('libreoffice', true, null, false, $llamadas)]);

        $this->assertStringContainsString('libreoffice', $auto->convertir('x'));
        $this->assertSame(['word', 'libreoffice'], $llamadas);
    }

    public function test_la_configuracion_puede_forzar_un_solo_motor(): void
    {
        config(['documentos.motor' => 'libreoffice']);

        $motores = ConversorPdfAutomatico::desdeConfiguracion()->nombresDeMotores();

        $this->assertSame(['libreoffice'], $motores);
        config(['documentos.motor' => 'auto']);
        $this->assertSame(['word', 'libreoffice'], ConversorPdfAutomatico::desdeConfiguracion()->nombresDeMotores());
    }

    public function test_la_orden_para_word_no_incluye_rutas_dentro_del_script(): void
    {
        $orden = (new WordConversor)->orden('C:\\Temp\\a b\\entrada.docx', 'C:\\Temp\\a b\\salida.pdf');

        $this->assertStringContainsString('ExportAsFixedFormat', $orden['script']);
        $this->assertStringContainsString('Word.Application', $orden['script']);
        // las rutas viajan por variables de entorno, no pegadas en el texto: no hay forma de inyectar comandos con un nombre raro
        $this->assertStringNotContainsString('entrada.docx', $orden['script']);
        $this->assertSame('C:\\Temp\\a b\\entrada.docx', $orden['env']['LG_ENTRADA']);
        $this->assertSame('C:\\Temp\\a b\\salida.pdf', $orden['env']['LG_SALIDA']);
    }

    public function test_fuera_de_windows_word_no_esta_disponible(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Esta comprobación es para otros sistemas.');
        }

        $this->assertFalse((new WordConversor)->disponible());
    }

    public function test_con_word_real_un_word_se_convierte_en_un_pdf_valido(): void
    {
        $word = new WordConversor;
        if (! $word->disponible()) {
            $this->markTestSkipped('Microsoft Word no está instalado en esta máquina.');
        }
        $w = new PhpWord;
        $w->addSection()->addText('Prueba con Word real');
        $tmp = tempnam(sys_get_temp_dir(), 'doc').'.docx';
        IOFactory::createWriter($w, 'Word2007')->save($tmp);

        $pdf = $word->convertir(file_get_contents($tmp), 'prueba');

        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
