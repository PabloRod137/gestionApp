<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\RegistroFacturacion;
use App\Support\Despacho;
use App\Support\Verifactu\NifEspanol;
use App\Support\Verifactu\SistemaInformatico;
use Illuminate\Console\Command;

class VerifactuEstado extends Command
{
    protected $signature = 'verifactu:estado';

    protected $description = 'Comprueba que el despacho puede facturar conforme a Veri*Factu y muestra el estado de los envíos';

    public function handle(): int
    {
        $problemas = [];
        $d = Despacho::datos();
        if (! NifEspanol::valido($d['nif'])) {
            if (NifEspanol::exigible()) {
                $problemas[] = 'El despacho no tiene un NIF válido (Configuración del panel o LEGAL_TAX_ID).';
            } else {
                $this->warn('Modo pruebas: el despacho no tiene NIF válido; los registros llevan un NIF provisional. Será obligatorio al pasar a producción.');
            }
        }
        $problemas = array_merge($problemas, SistemaInformatico::problemas());
        $s = SistemaInformatico::datos();
        if ($s['productor_por_defecto']) {
            $this->warn('Productor del programa: se usa el despacho por defecto. Confirmar con el gestor quién firma la declaración responsable.');
        }
        if (config('verifactu.activo')) {
            $cert = config('verifactu.certificado');
            if (! $cert || ! is_file($cert)) {
                $problemas[] = 'Envío activado pero falta el certificado electrónico (VERIFACTU_CERT_RUTA).';
            }
        } else {
            $this->warn('El envío a la AEAT está apagado (VERIFACTU_ACTIVO=false): los registros se generan y quedan pendientes.');
        }

        $this->line('Entorno: '.config('verifactu.entorno').' · Sistema: '.$s['nombre'].' '.$s['version'].' (instalación '.$s['num_instalacion'].')');
        $this->line('Facturas: '.Invoice::selectRaw('emision, count(*) n')->groupBy('emision')->pluck('n', 'emision')->map(fn ($n, $e) => "$e $n")->implode(', '));
        $this->line('Registros: '.(RegistroFacturacion::selectRaw('estado_envio, count(*) n')->groupBy('estado_envio')->pluck('n', 'estado_envio')->map(fn ($n, $e) => "$e $n")->implode(', ') ?: 'ninguno'));
        foreach (RegistroFacturacion::whereIn('estado_envio', ['rechazado', 'aceptado_errores'])->get() as $r) {
            $this->error("{$r->num_serie} ({$r->tipo}): {$r->estado_envio} · {$r->codigo_error} {$r->descripcion_error}");
        }

        foreach ($problemas as $p) {
            $this->error($p);
        }

        return $problemas ? self::FAILURE : self::SUCCESS;
    }
}
