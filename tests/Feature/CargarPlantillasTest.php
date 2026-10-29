<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/** despacho:plantillas carga los .docx de una carpeta en los modelos cuyo código coincide con el nombre del archivo. */
class CargarPlantillasTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->dir = sys_get_temp_dir().'/plt'.uniqid();
        mkdir($this->dir);
    }

    private function docx(string $nombre, string $texto = 'Hola ${cliente_nombre}'): void
    {
        $w = new PhpWord;
        $w->addSection()->addText($texto);
        IOFactory::createWriter($w, 'Word2007')->save("{$this->dir}/{$nombre}.docx");
    }

    public function test_carga_el_archivo_en_el_modelo_con_el_mismo_codigo(): void
    {
        DocumentTemplate::create(['codigo' => 'PLT_A', 'nombre' => 'A']);
        $this->docx('PLT_A');

        $this->artisan('despacho:plantillas', ['carpeta' => $this->dir])->assertSuccessful();

        $t = DocumentTemplate::where('codigo', 'PLT_A')->first();
        $this->assertNotNull($t->archivo);
        Storage::assertExists($t->archivo);
    }

    public function test_un_archivo_sin_modelo_o_que_no_es_word_se_avisa_y_no_se_carga(): void
    {
        DocumentTemplate::create(['codigo' => 'PLT_B', 'nombre' => 'B']);
        $this->docx('SIN_MODELO');
        file_put_contents("{$this->dir}/PLT_B.docx", 'esto no es un docx');

        $this->artisan('despacho:plantillas', ['carpeta' => $this->dir])->assertSuccessful();

        $this->assertNull(DocumentTemplate::where('codigo', 'PLT_B')->value('archivo'));
        $this->assertSame([], Storage::allFiles('plantillas'));
    }

    public function test_repetirlo_no_duplica_y_actualiza_el_archivo(): void
    {
        DocumentTemplate::create(['codigo' => 'PLT_A', 'nombre' => 'A']);
        $this->docx('PLT_A');

        $this->artisan('despacho:plantillas', ['carpeta' => $this->dir])->assertSuccessful();
        $this->artisan('despacho:plantillas', ['carpeta' => $this->dir])->assertSuccessful();

        $this->assertCount(1, Storage::allFiles('plantillas'));
    }

    public function test_falla_si_la_carpeta_no_existe(): void
    {
        $this->artisan('despacho:plantillas', ['carpeta' => '/no/existe'])->assertFailed();
    }
}
