<?php

namespace App\Console\Commands;

use App\Models\DocumentTemplate;
use App\Models\Service;
use Illuminate\Console\Command;

class AsignarRecetas extends Command
{
    protected $signature = 'despacho:recetas';

    protected $description = 'Asigna a cada servicio los modelos de documento de config/recetas.php (repetible; no quita ni duplica)';

    public function handle(): int
    {
        $n = 0;
        foreach ((array) config('recetas.modelos_por_servicio') as $codigoServicio => $modelos) {
            $servicio = Service::where('codigo', $codigoServicio)->first();
            if (! $servicio) {
                $this->warn("Servicio {$codigoServicio} no existe");

                continue;
            }
            foreach ($modelos as $codigo) {
                $modelo = DocumentTemplate::where('codigo', $codigo)->first();
                if (! $modelo) {
                    $this->warn("Modelo {$codigo} no existe");

                    continue;
                }
                $servicio->templates()->syncWithoutDetaching([$modelo->id]);
                $n++;
            }
        }
        $this->info("Asignaciones revisadas: {$n}");

        return self::SUCCESS;
    }
}
