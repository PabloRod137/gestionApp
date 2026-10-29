<?php

namespace App\Support;

use App\Models\DocumentTemplate;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\User;
use App\Support\Sync\PuenteSharePoint;
use App\Support\Pdf\ConversorPdf;
use App\Support\Pdf\PdfException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Rellena las plantillas Word del despacho con los datos del cliente y del expediente.
 * La plantilla lleva marcas del tipo ${cliente_nombre}; el texto, el formato y el logo son del despacho.
 * El programa no inventa nada: una marca que no conoce o un dato que no tiene se avisa antes de generar.
 */
class GeneradorDocumentos
{
    public function __construct(private ConversorPdf $conversor) {}

    /** Campos que se pueden usar en una plantilla, con su descripcion. */
    public const CAMPOS = [
        'cliente_nombre' => 'Nombre o razón social del cliente',
        'cliente_nif' => 'NIF / CIF del cliente',
        'cliente_codigo' => 'Código de cliente',
        'cliente_email' => 'Correo del cliente',
        'cliente_telefono' => 'Teléfono del cliente',
        'cliente_direccion' => 'Dirección del cliente',
        'expediente_numero' => 'Número de expediente',
        'expediente_titulo' => 'Título del expediente',
        'expediente_estado' => 'Estado del expediente',
        'servicio_nombre' => 'Servicio del expediente',
        'fecha_hoy' => 'Fecha de hoy',
        'despacho_nombre' => 'Nombre del despacho',
        'despacho_nif' => 'NIF del despacho',
        'despacho_direccion' => 'Dirección del despacho',
        'despacho_telefono' => 'Teléfono del despacho',
        'despacho_email' => 'Correo del despacho',
        'periodo' => 'Periodo del reporte (mes y año)',
        'ingresos' => 'Ingresos del periodo',
        'gastos' => 'Gastos del periodo',
        'saldo' => 'Saldo a cierre del periodo',
        'cuotas_pendientes' => 'Cuotas pendientes de cobro',
        'incidencias' => 'Incidencias y actuaciones realizadas',
        'proximas_actuaciones' => 'Próximas actuaciones',
    ];

    private const ESTADOS = ['abierto' => 'Abierto', 'en_proceso' => 'En proceso', 'pendiente_cliente' => 'Pendiente del cliente', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'];

    /** @return array<string, ?string> */
    public function valores(Expedient $expediente): array
    {
        $cliente = $expediente->client;
        $despacho = Despacho::datos();
        $vacio = fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        return [
            'cliente_nombre' => $vacio($cliente->razon_social),
            'cliente_nif' => $vacio($cliente->nif),
            'cliente_codigo' => $vacio($cliente->codigo),
            'cliente_email' => $vacio($cliente->email),
            'cliente_telefono' => $vacio($cliente->telefono),
            'cliente_direccion' => $vacio($cliente->direccion),
            'expediente_numero' => $vacio($expediente->codigo ?: $expediente->id),
            'expediente_titulo' => $vacio($expediente->titulo),
            'expediente_estado' => self::ESTADOS[$expediente->estado] ?? $vacio($expediente->estado),
            'servicio_nombre' => $vacio($expediente->service?->nombre),
            'fecha_hoy' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            'despacho_nombre' => $vacio($despacho['nombre']),
            'despacho_nif' => $vacio($despacho['nif']),
            'despacho_direccion' => $vacio($despacho['direccion']),
            'despacho_telefono' => $vacio($despacho['telefono']),
            'despacho_email' => $vacio($despacho['email']),
        ] + \App\Support\Comunidades\ReporteMensual::valores($expediente);
    }

    /** @return array<int, string> marcas ${...} que contiene el archivo */
    public function camposDe(string $rutaStorage): array
    {
        return $this->conCopiaLocal($rutaStorage, fn (string $local) => array_values(array_unique((new TemplateProcessor($local))->getVariables())));
    }

    /** Marcas que no son datos guardados (importe, firmante...): se piden a la persona al generar. */
    public function extras(DocumentTemplate $plantilla): array
    {
        return array_values(array_diff($this->camposDe($plantilla->archivo), array_keys(self::CAMPOS)));
    }

    /** Marcas conocidas que la plantilla usa pero para las que este expediente no tiene dato. */
    public function faltan(DocumentTemplate $plantilla, Expedient $expediente): array
    {
        $valores = $this->valores($expediente);

        return array_values(array_filter(
            array_intersect($this->camposDe($plantilla->archivo), array_keys(self::CAMPOS)),
            fn ($campo) => $valores[$campo] === null,
        ));
    }

    /**
     * Lo que hay que preguntar antes de generar: marcas que no son datos guardados (siempre) y datos guardados que faltan
     * (salvo los que ya se han escrito). Escribirlos en el momento nunca es obligatorio: un hueco en blanco se permite.
     *
     * @param  array<string, string>  $extras
     * @return array{pedir: array<int, string>, faltan: array<int, string>}
     */
    public function pendientes(DocumentTemplate $plantilla, Expedient $expediente, array $extras): array
    {
        $escritos = $this->escritos($extras);

        return [
            'pedir' => array_values(array_filter($this->extras($plantilla), fn ($c) => ! array_key_exists($c, $extras))),
            'faltan' => array_values(array_filter($this->faltan($plantilla, $expediente), fn ($c) => ! array_key_exists($c, $escritos))),
        ];
    }

    /** Lleva a la ficha del cliente los datos escritos a mano. Solo rellena huecos y solo si el formato es válido (misma regla que cuando los aporta el cliente). @return array<int, string> marcas guardadas */
    public function guardarEnFicha(Expedient $expediente, array $extras, ?User $por = null): array
    {
        $cliente = $expediente->client;
        $valores = [];
        foreach ($this->escritos($extras) as $marca => $valor) {
            if (str_starts_with($marca, 'cliente_')) {
                $valores[substr($marca, 8)] = $valor;
            }
        }
        $campos = DatosCliente::rellenar($cliente, $valores);
        if ($campos) {
            // lo que se completa en el momento también tiene que llegar a SharePoint
            PuenteSharePoint::datosDeCliente($cliente, $campos, $por);
        }

        return array_map(fn ($c) => 'cliente_'.$c, $campos);
    }

    /** Valores escritos a mano que no están en blanco (uno en blanco nunca borra un dato existente). */
    private function escritos(array $extras): array
    {
        return array_filter(array_map(fn ($v) => trim((string) $v), $extras), fn ($v) => $v !== '');
    }

    /** @param array<string, ?string> $extras valores escritos a mano: marcas que no son datos guardados o datos que faltaban en la ficha */
    public function generar(DocumentTemplate $plantilla, Expedient $expediente, ?User $por, array $extras = []): ExpedientDocument
    {
        Settings::setOutputEscapingEnabled(true);
        $valores = array_merge($this->valores($expediente), $this->escritos($extras));
        $destino = "expedientes/{$expediente->id}/".Str::slug($plantilla->nombre).'-'.now()->format('Ymd-His').'.docx';

        $contenido = $this->conCopiaLocal($plantilla->archivo, function (string $local) use ($valores) {
            $proc = new TemplateProcessor($local);
            foreach ($proc->getVariables() as $campo) {
                $proc->setValue($campo, $valores[$campo] ?? '');
            }
            $salida = tempnam(sys_get_temp_dir(), 'gen');
            $proc->saveAs($salida);
            $bytes = file_get_contents($salida);
            @unlink($salida);

            return $bytes;
        });
        Storage::put($destino, $contenido);

        return ExpedientDocument::create([
            'expedient_id' => $expediente->id,
            'client_id' => $expediente->client_id,
            'nombre' => $plantilla->nombre.' - '.($expediente->codigo ?: $expediente->id).'.docx',
            'ruta' => $destino,
            'tipo_mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'subido_por' => $por?->id,
            'visibilidad' => 'interno',
            'canal_entrada' => 'generado',
            'tipo_documento' => $plantilla->tipo_documento,
            'origen' => 'PLANTILLA:'.$plantilla->codigo,
        ]);
    }

    /**
     * Crea el PDF de un documento Word ya generado (misma carpeta y mismo nombre, con extensión .pdf). No usa IA: es una conversión.
     *
     * @throws PdfException si no se puede convertir
     */
    public function pdfDe(ExpedientDocument $docx, ?User $por, string $origen = 'PDF_AUTOMATICO'): ExpedientDocument
    {
        $pdf = $this->conversor->convertir(Storage::get($docx->ruta), (string) $docx->nombre);
        $ruta = preg_replace('/\.docx$/i', '', $docx->ruta).'.pdf';
        Storage::put($ruta, $pdf);

        return ExpedientDocument::create([
            'expedient_id' => $docx->expedient_id,
            'client_id' => $docx->client_id,
            'nombre' => preg_replace('/\.docx$/i', '', (string) $docx->nombre).'.pdf',
            'ruta' => $ruta,
            'tipo_mime' => 'application/pdf',
            'subido_por' => $por?->id,
            'visibilidad' => 'interno',
            'canal_entrada' => 'generado',
            'tipo_documento' => $docx->tipo_documento,
            'origen' => $origen,
        ]);
    }

    private function conCopiaLocal(string $rutaStorage, callable $fn): mixed
    {
        $local = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($local, Storage::get($rutaStorage));
        try {
            return $fn($local);
        } finally {
            @unlink($local);
        }
    }
}
