<?php

namespace App\Console\Commands;

use App\Support\Readiness;
use Illuminate\Console\Command;

class SiteReadiness extends Command
{
    protected $signature = 'site:readiness';

    protected $description = 'Comprueba si la web está lista para publicarse (datos legales, correo, pagos y modo producción)';

    public function handle(): int
    {
        $blockers = Readiness::blockers();
        if (! $blockers) {
            $this->info('La web está lista para publicarse.');

            return self::SUCCESS;
        }

        $this->error(count($blockers).' bloqueos antes de publicar:');
        foreach ($blockers as $key => $text) {
            $this->line(" - [$key] $text");
        }

        return self::FAILURE;
    }
}
