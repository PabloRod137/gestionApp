<?php

namespace App\Chat;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Message;
use App\Models\User;
use App\Support\DatosCliente;
use App\Support\PropuestaDocumental;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Asistente privado del Área Cliente, SIN inteligencia artificial: reconoce palabras clave y responde consultando
 * SOLO los datos del cliente que ha iniciado sesión. El cliente sale siempre de la sesión (nunca de lo que se escriba
 * ni del estado que viaja por el navegador) y cada expediente al que se hace referencia se vuelve a comprobar.
 *
 * No da asesoramiento jurídico ni inventa nada: lo que no sabe, lo deriva al asesor con un mensaje en el chat del
 * expediente. Las solicitudes de certificado también son mensajes: las prepara y revisa una persona del despacho.
 */
class ChatCliente
{
    public const MAX_TEXT = 500;

    private const MAX_STATE_BYTES = 300;

    private const ESTADOS = [
        'abierto' => 'Abierto, pendiente de empezar',
        'en_proceso' => 'En curso',
        'pendiente_cliente' => 'Esperando documentación o respuesta tuya',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
    ];

    private const ACTIVOS = ['abierto', 'en_proceso', 'pendiente_cliente'];

    /** Intenciones: palabras clave → acción. Gana la palabra clave más larga que coincida. */
    private const INTENTS = [
        'menu' => ['hola', 'buenos dias', 'buenas tardes', 'buenas noches', 'ayuda', 'menu', 'inicio', 'que puedes hacer'],
        'expedientes' => ['expediente', 'expedientes', 'caso', 'casos', 'asunto', 'asuntos', 'como va', 'como van', 'estado', 'situacion', 'en que punto', 'novedades', 'plazo', 'plazos', 'cuanto tarda', 'cuando'],
        'documentos' => ['documento', 'documentos', 'archivo', 'archivos', 'papeles', 'descargar', 'descarga', 'pdf'],
        'pendiente' => ['que falta', 'falta', 'pendiente', 'pendientes', 'aportar', 'entregar', 'que necesitais', 'que necesitas', 'que tengo que'],
        'citas' => ['cita', 'citas', 'reunion', 'reuniones', 'proxima cita'],
        'facturas' => ['factura', 'facturas', 'recibo', 'recibos', 'pagar', 'pago', 'cuanto debo', 'deuda', 'cobro'],
        'certificado' => ['certificado', 'certificados', 'justificante', 'justificantes', 'acreditacion'],
        'datos' => ['mis datos', 'datos personales', 'mi nif', 'mi dni', 'mi telefono', 'mi direccion', 'mi correo', 'mi email', 'que datos faltan', 'completar datos', 'rellenar'],
        'mensaje' => ['hablar con', 'escribir', 'mensaje', 'abogado', 'abogada', 'asesor', 'asesora', 'contactar', 'llamar', 'consulta', 'duda'],
    ];

    /** Cada pulsación de botón es una acción conocida; cualquier otra se descarta. */
    private const ACCIONES = ['menu', 'cancel', 'expedientes', 'expediente', 'documentos', 'pendiente', 'citas', 'facturas', 'certificado', 'mensaje', 'datos', 'elegir_certificado', 'elegir_mensaje'];

    /** El cliente y el usuario de la sesión en el turno actual. */
    private Client $cliente;

    private User $usuario;

    /** Qué se ha hecho en este turno (solo el nombre de la acción, nunca el contenido), para la auditoría. */
    private string $intencion = 'texto_libre';

    /** @return array{messages: array<int, array>, state: ?array, intent: string} */
    public function handle(Client $cliente, User $usuario, mixed $input, mixed $state = null): array
    {
        $this->cliente = $cliente;
        $this->usuario = $usuario;
        $this->intencion = 'texto_libre';
        $input = $this->validar($input);
        $state = $this->limpiarEstado($state);
        $resultado = $this->encaminar($input, $state);

        return ['messages' => $this->conNavegacion($resultado['messages']), 'state' => $resultado['state'] ?? null, 'intent' => $this->intencion];
    }

    /** Acepta {text} o {action:{id,payload?}} y descarta cualquier otra cosa. */
    private function validar(mixed $input): array
    {
        if (! is_array($input)) {
            throw new InvalidChatInput();
        }
        if (array_key_exists('action', $input)) {
            $action = $input['action'];
            $id = is_array($action) ? ($action['id'] ?? null) : null;
            $payload = is_array($action) ? ($action['payload'] ?? null) : null;
            if (! is_string($id) || ! in_array($id, self::ACCIONES, true)) {
                throw new InvalidChatInput();
            }
            // el único dato que viaja con un botón es el número de un expediente
            if ($payload !== null && (! is_string($payload) || ! preg_match('/^\d{1,9}$/', $payload))) {
                throw new InvalidChatInput();
            }

            return ['action' => $id, 'payload' => $payload === null ? null : (int) $payload];
        }
        $text = $input['text'] ?? null;
        if (! is_string($text) || trim($text) === '' || mb_strlen(trim($text)) > self::MAX_TEXT) {
            throw new InvalidChatInput();
        }

        return ['text' => trim($text)];
    }

    /** Solo se acepta el estado de una solicitud en curso, con la forma esperada. El expediente se vuelve a comprobar al usarlo. */
    private function limpiarEstado(mixed $state): ?array
    {
        if (! is_array($state) || array_is_list($state) || strlen((string) json_encode($state)) > self::MAX_STATE_BYTES) {
            return null;
        }
        if (! in_array($state['flow'] ?? null, ['certificado', 'mensaje'], true) || ($state['step'] ?? null) !== 'texto' || ! is_int($state['expediente'] ?? null)) {
            return null;
        }

        return ['flow' => $state['flow'], 'step' => 'texto', 'expediente' => $state['expediente']];
    }

    private function encaminar(array $input, ?array $state): array
    {
        if (($input['action'] ?? null) === 'cancel') {
            $this->intencion = 'cancelar';

            return ['messages' => [$this->menu()], 'state' => null];
        }
        // una solicitud empezada espera el texto del cliente; un botón la abandona
        if ($state && isset($input['text'])) {
            return $this->enviarSolicitud($state, $input['text']);
        }
        if (isset($input['action'])) {
            return $this->accion($input['action'], $input['payload'] ?? null);
        }
        $intencion = $this->reconocer($input['text'], self::INTENTS);

        return $intencion ? $this->accion($intencion, null) : $this->noEntendido();
    }

    private function accion(string $id, ?int $expedienteId): array
    {
        $this->intencion = $id;

        return match ($id) {
            'menu' => ['messages' => [$this->bot(strtr(config('chat.cliente.greeting'), [':name' => $this->usuario->name])), $this->menu()]],
            'expedientes' => $this->expedientes(),
            'expediente' => $this->expediente($expedienteId),
            'documentos' => $this->documentos(),
            'pendiente' => $this->pendiente(),
            'datos' => $this->datos(),
            'citas' => $this->citas(),
            'facturas' => $this->facturas(),
            'certificado', 'mensaje' => $this->empezarSolicitud($id, null),
            'elegir_certificado' => $this->empezarSolicitud('certificado', $expedienteId),
            'elegir_mensaje' => $this->empezarSolicitud('mensaje', $expedienteId),
            default => $this->noEntendido(),
        };
    }

    // ── Palabras clave ─────────────────────────────────────────────

    private function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $texto)));
    }

    /** La intención cuya palabra clave coincidente (palabras completas) es la más larga, o null. */
    private function reconocer(string $texto, array $intents): ?string
    {
        $pajar = ' '.$this->normalizar(mb_substr($texto, 0, self::MAX_TEXT)).' ';
        $mejor = null;
        $largo = 0;
        foreach ($intents as $id => $claves) {
            foreach ($claves as $clave) {
                $clave = $this->normalizar($clave);
                if ($clave !== '' && str_contains($pajar, " $clave ") && strlen($clave) > $largo) {
                    $mejor = $id;
                    $largo = strlen($clave);
                }
            }
        }

        return $mejor;
    }

    // ── Respuestas ─────────────────────────────────────────────────

    private function bot(string $texto, array $extra = []): array
    {
        return ['role' => 'bot', 'text' => $texto] + $extra;
    }

    private function menu(): array
    {
        return $this->bot(config('chat.cliente.menu_text'), ['buttons' => config('chat.cliente.menu_buttons')]);
    }

    private function noEntendido(): array
    {
        $this->intencion = 'no_entendido';

        return ['messages' => [$this->bot(config('chat.cliente.fallback'), ['buttons' => [['id' => 'mensaje', 'label' => 'Escribir al despacho'], ['id' => 'menu', 'label' => 'Ver el menú']]])], 'state' => null];
    }

    /** La última respuesta siempre termina con botones: el cliente nunca se queda sin salida. */
    private function conNavegacion(array $mensajes): array
    {
        $ultimo = end($mensajes);
        if ($ultimo && empty($ultimo['buttons'])) {
            $mensajes[array_key_last($mensajes)] = $ultimo + ['buttons' => config('chat.cliente.menu_buttons')];
        }

        return $mensajes;
    }

    private function fecha(mixed $valor): string
    {
        return $valor ? Carbon::parse($valor)->format('d/m/Y') : '—';
    }

    private function euros(mixed $importe): string
    {
        return number_format((float) $importe, 2, ',', '.').' €';
    }

    /** Todos los expedientes son del cliente de la sesión. */
    private function expedientesDelCliente()
    {
        return Expedient::where('client_id', $this->cliente->id);
    }

    private function expedientes(): array
    {
        $lista = $this->expedientesDelCliente()->with('service')->orderByDesc('fecha_apertura')->orderByDesc('id')->take(8)->get();
        if ($lista->isEmpty()) {
            return ['messages' => [$this->bot('Todavía no tienes expedientes abiertos con nosotros.')]];
        }
        $botones = $lista->map(fn (Expedient $e) => ['id' => 'expediente', 'payload' => (string) $e->id, 'label' => 'Ver '.$e->codigo])->all();

        return ['messages' => [$this->bot('Estos son tus expedientes. Pulsa uno para ver cómo va.', [
            'cards' => $lista->map(fn (Expedient $e) => [
                'title' => $e->codigo.' · '.$e->titulo,
                'lines' => array_values(array_filter([$e->service?->nombre, self::ESTADOS[$e->estado] ?? $e->estado])),
            ])->all(),
            'buttons' => [...$botones, ['id' => 'menu', 'label' => 'Volver al menú']],
        ])]];
    }

    private function expediente(?int $id): array
    {
        $e = $id ? $this->expedientesDelCliente()->with(['service', 'worker'])->find($id) : null;
        if (! $e) {
            return $this->expedientes();
        }
        $docs = $e->documents()->where('visibilidad', 'cliente')->count();
        $falta = $this->requisitos($e)->count();
        $lineas = array_values(array_filter([
            'Estado: '.(self::ESTADOS[$e->estado] ?? $e->estado),
            $e->service ? 'Servicio: '.$e->service->nombre : null,
            'Abierto el '.$this->fecha($e->fecha_apertura),
            $e->fecha_cierre ? 'Cerrado el '.$this->fecha($e->fecha_cierre) : 'Última actualización: '.$this->fecha($e->updated_at),
            $e->worker ? 'Tu asesor: '.$e->worker->name : null,
            $docs.' documento(s) disponible(s) para ti',
            $falta > 0 ? $falta.' documento(s) que suele pedir este trámite' : null,
        ]));

        return ['messages' => [$this->bot('Así está «'.$e->titulo.'»:', [
            'cards' => [['title' => $e->codigo.' · '.$e->titulo, 'lines' => $lineas, 'href' => '/cliente/expedientes/'.$e->id]],
            'buttons' => [['id' => 'documentos', 'label' => 'Mis documentos'], ['id' => 'pendiente', 'label' => 'Qué falta aportar'], ['id' => 'elegir_mensaje', 'payload' => (string) $e->id, 'label' => 'Escribir a mi asesor']],
        ]), $this->bot('Los plazos legales concretos los confirma tu asesor: pregúntale si necesitas una fecha exacta.')]];
    }

    private function documentos(): array
    {
        $ids = $this->expedientesDelCliente()->pluck('id');
        $docs = ExpedientDocument::whereIn('expedient_id', $ids)->where('visibilidad', 'cliente')->with('expedient')->orderByDesc('created_at')->take(8)->get();
        if ($docs->isEmpty()) {
            return ['messages' => [$this->bot('Todavía no tienes documentos disponibles aquí. Cuando el despacho te los deje, aparecerán en esta lista.')]];
        }

        return ['messages' => [$this->bot('Estos son tus documentos más recientes:', ['cards' => $docs->map(fn (ExpedientDocument $d) => array_filter([
            'title' => $d->nombre,
            'lines' => [$d->expedient?->codigo.' · '.$this->fecha($d->created_at)],
            // solo se enlaza lo que se puede descargar de verdad
            'href' => $d->ruta ? '/cliente/documentos/'.$d->id : null,
        ]))->all()])]];
    }

    /** Documentación obligatoria que suele pedir el servicio de un expediente. */
    private function requisitos(Expedient $e)
    {
        return PropuestaDocumental::para($e)['aportar']->where('obligatorio', true)->values();
    }

    private function pendiente(): array
    {
        $activos = $this->expedientesDelCliente()->whereIn('estado', self::ACTIVOS)->with('service.requirements')->orderByDesc('id')->take(5)->get();
        $tarjetas = [];
        foreach ($activos as $e) {
            $req = $this->requisitos($e);
            if ($req->isNotEmpty()) {
                $tarjetas[] = ['title' => $e->codigo.' · '.$e->titulo, 'lines' => $req->take(6)->pluck('descripcion')->all()];
            }
        }
        $esperando = $activos->where('estado', 'pendiente_cliente');
        $texto = $tarjetas
            ? 'Esto es lo que suele pedir cada trámite. Si ya lo has entregado, el despacho lo tiene en cuenta: no hace falta que lo vuelvas a mandar.'
            : 'Ahora mismo no tengo documentación pendiente que mostrarte.';
        if ($esperando->isNotEmpty()) {
            $texto .= ' Además, el despacho está esperando algo de ti en: '.$esperando->pluck('codigo')->implode(', ').'.';
        }

        return ['messages' => [$this->bot($texto, $tarjetas ? ['cards' => $tarjetas] : [])]];
    }

    private function datos(): array
    {
        $faltan = DatosCliente::faltan($this->cliente);
        $tarjeta = ['title' => 'Mis datos', 'lines' => [], 'href' => '/cliente/mis-datos'];
        if (! $faltan) {
            return ['messages' => [$this->bot('Tu ficha está completa. Si algún dato ha cambiado, escribe a tu asesor.', ['cards' => [$tarjeta]])]];
        }

        return ['messages' => [$this->bot('Para preparar tus documentos nos faltan: '.implode(', ', $faltan).'. Puedes completarlos tú mismo en un momento, en un formulario.', ['cards' => [$tarjeta]])]];
    }

    private function citas(): array
    {
        $citas = $this->cliente->appointments()->with('service')->where('fecha', '>=', now()->toDateString())->whereIn('estado', ['pendiente', 'confirmada'])
            ->orderBy('fecha')->orderBy('hora')->take(5)->get();
        if ($citas->isEmpty()) {
            return ['messages' => [$this->bot('No tienes citas próximas. Puedes pedir una desde «Citas Previa», en el menú de la izquierda.', ['cards' => [['title' => 'Pedir una cita', 'lines' => [], 'href' => '/cliente/citas']]])]];
        }

        return ['messages' => [$this->bot('Tus próximas citas:', ['cards' => $citas->map(fn ($c) => [
            'title' => $this->fecha($c->fecha).' a las '.substr((string) $c->hora, 0, 5),
            'lines' => array_values(array_filter([$c->service?->nombre, $c->estado === 'pendiente' ? 'Pendiente de confirmar por el despacho' : 'Confirmada'])),
        ])->all()])]];
    }

    private function facturas(): array
    {
        $pendientes = $this->cliente->invoices()->pendientesDeCobro()->orderByDesc('fecha')->take(6)->get();
        if ($pendientes->isEmpty()) {
            return ['messages' => [$this->bot('No tienes facturas pendientes de pago.', ['cards' => [['title' => 'Ver todas mis facturas', 'lines' => [], 'href' => '/cliente/facturas']]])]];
        }
        $total = $this->cliente->invoices()->pendientesDeCobro()->sum('total');

        return ['messages' => [$this->bot('Tienes '.$pendientes->count().' factura(s) pendiente(s) de pago, '.$this->euros($total).' en total:', ['cards' => $pendientes->map(fn ($f) => [
            'title' => 'Factura '.$f->numero,
            'lines' => [$this->fecha($f->fecha).' · '.$this->euros($f->total)],
            'href' => '/cliente/facturas/'.$f->id.'/pdf',
        ])->all()])]];
    }

    // ── Certificado y mensaje al asesor: el texto se guarda como mensaje del expediente ──

    private function empezarSolicitud(string $flujo, ?int $expedienteId): array
    {
        $this->intencion = $flujo;
        $propios = $this->expedientesDelCliente()->orderByDesc('id')->take(6)->get();
        if ($propios->isEmpty()) {
            return ['messages' => [$this->bot(strtr(config('chat.cliente.sin_expediente'), [':phone' => config('site.phone'), ':email' => config('site.email')]))]];
        }
        $e = $expedienteId ? $propios->firstWhere('id', $expedienteId) : ($propios->count() === 1 ? $propios->first() : null);
        if (! $e) {
            return ['messages' => [$this->bot('¿Sobre qué expediente?', ['buttons' => [
                ...$propios->map(fn (Expedient $x) => ['id' => 'elegir_'.$flujo, 'payload' => (string) $x->id, 'label' => $x->codigo.' · '.Str::limit($x->titulo, 28)])->all(),
                ['id' => 'cancel', 'label' => 'Cancelar'],
            ]])]];
        }

        return [
            'messages' => [$this->bot(config("chat.cliente.pregunta_$flujo").' (Expediente '.$e->codigo.')', ['buttons' => [['id' => 'cancel', 'label' => 'Cancelar']]])],
            'state' => ['flow' => $flujo, 'step' => 'texto', 'expediente' => $e->id],
        ];
    }

    private function enviarSolicitud(array $state, string $texto): array
    {
        // el expediente que viaja en el estado se vuelve a comprobar: tiene que ser de este cliente
        $e = $this->expedientesDelCliente()->find($state['expediente']);
        if (! $e) {
            return ['messages' => [$this->menu()], 'state' => null];
        }
        $destino = $e->worker_id ?: User::role('admin')->value('id');
        if (! $destino) {
            return ['messages' => [$this->bot(strtr(config('chat.cliente.sin_expediente'), [':phone' => config('site.phone'), ':email' => config('site.email')]))], 'state' => null];
        }
        $esCertificado = $state['flow'] === 'certificado';
        Message::create([
            'expedient_id' => $e->id, 'from_user_id' => $this->usuario->id, 'to_user_id' => $destino,
            'contenido' => ($esCertificado ? 'Solicitud de certificado (desde el asistente): ' : '').$texto, 'leido' => false,
        ]);
        $this->intencion = $esCertificado ? 'certificado_solicitado' : 'mensaje_enviado';

        return ['messages' => [$this->bot(config($esCertificado ? 'chat.cliente.hecho_certificado' : 'chat.cliente.hecho_mensaje'), [
            'cards' => [['title' => $e->codigo.' · '.$e->titulo, 'lines' => [], 'href' => '/cliente/expedientes/'.$e->id]],
        ])], 'state' => null];
    }
}
