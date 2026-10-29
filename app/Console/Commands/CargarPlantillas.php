<?php

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CargarPlantillas extends Command
{
    protected $signature = 'despacho:plantillas {carpeta : Carpeta con los modelos Word (.docx), uno por código de modelo}';

    protected $description = 'Carga los .docx de una carpeta en los modelos de documento cuyo código coincide con el nombre del archivo (repetible)';

    public function handle(): int
    {
        $carpeta = (string) $this->argument('carpeta');
        if (! is_dir($carpeta)) {
            $this->error("No existe la carpeta: {$carpeta}");

            return self::FAILURE;
        }
        $n = 0;
        foreach (glob(rtrim($carpeta, '/\\').DIRECTORY_SEPARATOR.'*.docx') ?: [] as $ruta) {
            $codigo = pathinfo($ruta, PATHINFO_FILENAME);
            $modelo = DocumentTemplate::where('codigo', $codigo)->first();
            if (! $modelo) {
                $this->warn("{$codigo}.docx: no hay ningún modelo con ese código; se ignora");

                continue;
            }
            $zip = new \ZipArchive;
            if ($zip->open($ruta) !== true || $zip->locateName('word/document.xml') === false) {
                $this->warn("{$codigo}.docx: no es un documento Word válido; se ignora");

                continue;
            }
            $zip->close();
            $destino = "plantillas/{$codigo}.docx";
            Storage::put($destino, (string) file_get_contents($ruta));
            $modelo->update(['archivo' => $destino]);
            $n++;
        }
        $this->info("Modelos cargados: {$n}");

        return self::SUCCESS;
    }
}
