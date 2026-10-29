<?php

namespace App\Chat;

use App\Mail\ContactReceived;
use App\Models\ContactRequest;
use App\Shop\Catalog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Asistente público de la web, SIN inteligencia artificial: busca palabras clave y responde con textos
 * predefinidos (config/chat.php). No recibe ni consulta datos de clientes; solo información ya publicada.
 *
 * El estado del flujo guiado «que me contacte el despacho» lo guarda el navegador, así que se vuelve a validar
 * entero en cada paso y no se guarda nada sin pulsar el botón de consentimiento.
 */
class PublicChat
{
    public const MAX_TEXT = 500;

    private const MAX_PAYLOAD = 200;

    private const MAX_STATE_BYTES = 1000;

    private const NAME = '/^[\p{L}\p{M}\'’ .-]{2,100}$/u';

    private const PHONE = '/^\+?[\d\s.-]{9,20}$/';

    private const LEAD_KEYWORDS = ['que me contacte', 'quiero que me llamen', 'dejar mis datos', 'llamadme', 'contactadme', 'me pueden llamar', 'que me llamen'];

    /** Intenciones: palabras clave → qué responde. */
    private const INTENTS = [
        'menu' => ['hola', 'buenos dias', 'buenas tardes', 'buenas noches', 'ayuda', 'menu', 'inicio', 'que haceis'],
        'services' => ['servicio', 'servicios', 'que ofreceis', 'a que os dedicais', 'areas', 'inmueble', 'inmuebles', 'comunidad', 'comunidades', 'fiscal', 'laboral', 'legal', 'vehiculos', 'activos', 'seguros', 'seguro', 'inmobiliario'],
        'prices' => ['precio', 'precios', 'cuanto cuesta', 'tarifa', 'tarifas', 'coste', 'cuanto vale', 'presupuesto'],
        'booking' => ['reservar', 'reserva', 'cita', 'citas', 'cita previa', 'pedir cita', 'agendar'],
        'hours' => ['horario', 'horarios', 'a que hora', 'cuando abris', 'abierto', 'abrir'],
        'contact' => ['telefono', 'llamar', 'contacto', 'contactar', 'correo', 'email', 'donde estais', 'ubicacion', 'direccion'],
        'client_area' => ['area cliente', 'mi cuenta', 'registrarme', 'registro', 'iniciar sesion', 'acceder', 'mis expedientes', 'documentos'],
    ];

    /** @return array{messages: array<int, array>, state: ?array} */
    public function handle(mixed $input, mixed $state = null): array
    {
        $input = $this->validate($input);
        $state = $this->sanitizeState($state);
        $result = $this->route($input, $state);

        return ['messages' => $this->withNavigation($result['messages']), 'state' => $result['state'] ?? null];
    }

    /** Acepta {text} o {action:{id,payload?}} y descarta cualquier otra cosa. */
    private function validate(mixed $input): array
    {
        if (! is_array($input)) {
            throw new InvalidChatInput();
        }
        if (array_key_exists('action', $input)) {
            $action = $input['action'];
            $id = is_array($action) ? ($action['id'] ?? null) : null;
            $payload = is_array($action) ? ($action['payload'] ?? null) : null;
            if (! is_string($id) || ! preg_match('/^[a-z_]{1,40}$/', $id)) {
                throw new InvalidChatInput();
            }
            if ($payload !== null && (! is_string($payload) || mb_strlen($payload) > self::MAX_PAYLOAD)) {
                throw new InvalidChatInput();
            }

            return ['action' => $id];
        }
        $text = $input['text'] ?? null;
        if (! is_string($text) || trim($text) === '' || mb_strlen(trim($text)) > self::MAX_TEXT) {
            throw new InvalidChatInput();
        }

        return ['text' => trim($text)];
    }

    /** Solo se acepta un estado con la forma esperada y un tamaño razonable. */
    private function sanitizeState(mixed $state): ?array
    {
        if (! is_array($state) || array_is_list($state) || ! is_string($state['flow'] ?? null) || ! is_string($state['step'] ?? null)) {
            return null;
        }
        if (strlen($state['step']) > 20 || strlen(json_encode($state)) > self::MAX_STATE_BYTES) {
            return null;
        }

        return $state['flow'] === 'lead' ? $state : null;
    }

    private function route(array $input, ?array $state): array
    {
        if (($input['action'] ?? null) === 'cancel') {
            return ['messages' => [$this->menu()], 'state' => null];
        }
        if ($state) {
            return $this->leadStep($input, $state);
        }
        if (isset($input['action'])) {
            if ($input['action'] === 'menu') {
                return ['messages' => [$this->menu()]];
            }
            if ($input['action'] === 'lead') {
                return $this->leadStart();
            }
            if (isset(self::INTENTS[$input['action']])) {
                return $this->intent($input['action']);
            }

            return $this->fallback();
        }
        if ($intent = $this->match($input['text'], self::INTENTS)) {
            return $this->intent($intent);
        }
        if ($this->match($input['text'], ['lead' => self::LEAD_KEYWORDS])) {
            return $this->leadStart();
        }

        return $this->fallback();
    }

    // ── Palabras clave ─────────────────────────────────────────────

    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $text)));
    }

    /** La intención cuya palabra clave coincidente (palabras completas) es la más larga, o null. */
    private function match(string $text, array $intents): ?string
    {
        $haystack = ' '.$this->normalize(mb_substr($text, 0, self::MAX_TEXT)).' ';
        $best = null;
        $bestLength = 0;
        foreach ($intents as $id => $keywords) {
            foreach ($keywords as $keyword) {
                $key = $this->normalize($keyword);
                if ($key !== '' && str_contains($haystack, " $key ") && strlen($key) > $bestLength) {
                    $best = $id;
                    $bestLength = strlen($key);
                }
            }
        }

        return $best;
    }

    // ── Respuestas ─────────────────────────────────────────────────

    private function bot(string $text, array $extra = []): array
    {
        return ['role' => 'bot', 'text' => $text] + $extra;
    }

    private function link(string $name): array
    {
        return ['title' => config("chat.links.$name.title"), 'lines' => [], 'href' => config("chat.links.$name.href")];
    }

    private function menu(): array
    {
        return $this->bot(config('chat.menu_text'), ['buttons' => config('chat.menu_buttons')]);
    }

    private function fallback(): array
    {
        return ['messages' => [$this->bot(config('chat.fallback'), ['buttons' => config('chat.fallback_buttons')])], 'state' => null];
    }

    private function fill(string $template, array $values): string
    {
        return strtr($template, $values);
    }

    private function intent(string $id): array
    {
        $buttons = config('chat.menu_buttons');
        $messages = match ($id) {
            'menu' => [$this->bot(config('chat.greeting')), $this->menu()],
            'services' => [$this->bot(config('chat.services'), ['cards' => [$this->link('services')], 'buttons' => $buttons])],
            'prices' => [$this->bot($this->prices().' '.config('chat.prices_note'), ['buttons' => $buttons])],
            'booking' => [$this->bot(config('chat.booking'), ['cards' => [$this->link('booking')], 'buttons' => $buttons])],
            'hours' => [$this->bot($this->fill(config('chat.hours'), [':hours' => config('site.hours')]), ['buttons' => $buttons])],
            'contact' => [$this->bot($this->fill(config('chat.contact'), [':phone' => config('site.phone'), ':email' => config('site.email')]), ['cards' => [$this->link('contact')], 'buttons' => $buttons])],
            'client_area' => [$this->bot(config('chat.client_area'), ['cards' => [$this->link('client_area')], 'buttons' => $buttons])],
        };

        return ['messages' => $messages, 'state' => null];
    }

    /** Los precios se leen del catálogo de la tienda: no se duplican. */
    private function prices(): string
    {
        $euros = fn (int $cents) => rtrim(rtrim(number_format($cents / 100, 2, ',', '.'), '0'), ',').' €';
        $lines = array_map(fn (array $area) => explode(' - ', $area['name'])[0].': '
            .$euros($area['modes']['telematica']['price_cents']).' telemática, '.$euros($area['modes']['presencial']['price_cents']).' presencial', Catalog::areas());

        return 'Consultas iniciales: '.implode('; ', $lines).'.';
    }

    /** La última respuesta siempre termina con botones: el visitante nunca se queda sin salida. */
    private function withNavigation(array $messages): array
    {
        $last = end($messages);
        if ($last && empty($last['buttons'])) {
            $messages[array_key_last($messages)] = $last + ['buttons' => config('chat.menu_buttons')];
        }

        return $messages;
    }

    // ── Flujo «que me contacte el despacho»: nombre → contacto → consentimiento → se guarda ──

    private function leadBot(string $text, array $buttons = [], array $extra = []): array
    {
        return $this->bot($text, ['buttons' => [...$buttons, ['id' => 'cancel', 'label' => 'Cancelar']]] + $extra);
    }

    private function leadStart(?string $intro = null): array
    {
        return ['messages' => [$this->leadBot($intro ?? config('chat.lead.ask_name'))], 'state' => ['flow' => 'lead', 'step' => 'name', 'data' => []]];
    }

    private function consentPrompt(): array
    {
        return $this->leadBot(config('chat.lead.ask_consent'), [['id' => 'lead_consent', 'label' => config('chat.lead.consent_button')]], ['cards' => [$this->link('privacy')]]);
    }

    private function parseContact(string $text): ?array
    {
        $value = trim($text);
        if (strlen($value) <= 180 && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['email' => strtolower($value)];
        }
        $digits = strlen(preg_replace('/\D/', '', $value));
        if (preg_match(self::PHONE, $value) && $digits >= 9 && $digits <= 15) {
            return ['phone' => $value];
        }

        return null;
    }

    /** El estado viaja por el navegador: se vuelve a validar entero antes de usarlo. */
    private function cleanData(mixed $data): array
    {
        $source = is_array($data) ? $data : [];
        $text = fn ($value, int $max) => is_string($value) && strlen($value) <= $max ? $value : null;

        return ['name' => $text($source['name'] ?? null, 100), 'email' => $text($source['email'] ?? null, 180), 'phone' => $text($source['phone'] ?? null, 40)];
    }

    private function validData(array $data): bool
    {
        return $data['name'] && preg_match(self::NAME, $data['name'])
            && (($data['email'] && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) || ($data['phone'] && preg_match(self::PHONE, $data['phone'])));
    }

    private function leadStep(array $input, array $state): array
    {
        $data = $this->cleanData($state['data'] ?? []);
        $keep = ['flow' => 'lead'];
        switch ($state['step']) {
            case 'name':
                $text = $input['text'] ?? null;
                if ($text && preg_match(self::NAME, $text)) {
                    return ['messages' => [$this->leadBot(config('chat.lead.ask_contact'))], 'state' => $keep + ['step' => 'contact', 'data' => ['name' => $text]]];
                }

                return ['messages' => [$this->leadBot(config('chat.lead.retry_name'))], 'state' => $keep + ['step' => 'name', 'data' => []]];
            case 'contact':
                if (! $data['name'] || ! preg_match(self::NAME, $data['name'])) {
                    return $this->leadStart();
                }
                $contact = isset($input['text']) ? $this->parseContact($input['text']) : null;
                if ($contact) {
                    return ['messages' => [$this->consentPrompt()], 'state' => $keep + ['step' => 'consent', 'data' => ['name' => $data['name']] + $contact]];
                }

                return ['messages' => [$this->leadBot(config('chat.lead.retry_contact'))], 'state' => $keep + ['step' => 'contact', 'data' => ['name' => $data['name']]]];
            case 'consent':
                if (! $this->validData($data)) {
                    return $this->leadStart(config('chat.lead.restart'));
                }
                if (($input['action'] ?? null) === 'lead_consent') {
                    $reference = $this->saveLead($data);

                    return ['messages' => [$this->bot($this->fill(config('chat.lead.done'), [':name' => $data['name'], ':reference' => $reference]), ['buttons' => config('chat.menu_buttons')])], 'state' => null];
                }

                return ['messages' => [$this->consentPrompt()], 'state' => $state];
            default:
                return $this->leadStart();
        }
    }

    /** Guarda la solicitud (ya con consentimiento) y avisa al despacho. Devuelve la referencia. */
    private function saveLead(array $data): string
    {
        $contact = ContactRequest::create([
            'nombre' => $data['name'], 'email' => $data['email'] ?: 'sin-correo@chat.invalid', 'telefono' => $data['phone'],
            'servicio_interes' => 'Chat web: contacto solicitado', 'mensaje' => 'Solicitud de contacto desde el asistente de la web.', 'estado' => 'nuevo',
        ]);
        try {
            Mail::to(config('site.email'))->send(new ContactReceived($contact));
        } catch (\Throwable $e) {
            logger()->warning('No se pudo avisar de un contacto del chat: '.$e->getMessage());
        }

        return sprintf('CON-%06d', $contact->id);
    }
}
