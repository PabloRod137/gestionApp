<?php

namespace App\Console\Commands;

use App\Support\Cobros\PrepararFactura;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class FacturasFinDeMes extends Command
{
    protected $signature = 'facturas:fin-de-mes {--mes= : Mes a facturar (AAAA-MM); por defecto el actual}';

    protected $description = 'Prepara borradores de factura de las cuotas periódicas de los clientes que tocan este mes (no emite nada)';

    public function handle(): int
    {
        $mes = $this->option('mes') ? Carbon::createFromFormat('Y-m', $this->option('mes'))->startOfMonth() : now('Europe/Madrid')->startOfMonth();
        $n = PrepararFactura::finDeMes($mes);
        $this->info($n ? "$n borrador(es) preparado(s) para {$mes->format('Y-m')}. Revísalos y emítelos en Facturación." : "No había cuotas pendientes para {$mes->format('Y-m')}.");

        return self::SUCCESS;
    }
}
