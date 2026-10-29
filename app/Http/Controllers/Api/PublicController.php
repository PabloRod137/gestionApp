<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ContactReceived;
use App\Mail\ReservationReceived;
use App\Models\Appointment;
use App\Models\ContactRequest;
use App\Models\Service;
use App\Shop\Availability;
use App\Shop\BookingKeys;
use App\Shop\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/** API pública de la web: servicios, disponibilidad, contacto y reservas de cita. */
class PublicController extends Controller
{
    private const RECEIVED = 'Solicitud recibida. El despacho la revisará y contactará contigo.';

    public function services(): JsonResponse
    {
        return response()->json(['ok' => true, 'services' => Catalog::publicServices()]);
    }

    public function availability(Request $request): JsonResponse
    {
        $area = Catalog::area((string) $request->query('service'));
        $date = (string) $request->query('date');
        if (! $area || ! Availability::parseDate($date)) {
            return response()->json(['ok' => false, 'error' => 'invalid_service_or_date'], 400);
        }

        return response()->json([
            'ok' => true, 'service' => $area['id'], 'date' => $date,
            'durationMinutes' => $area['duration_minutes'], 'slots' => Availability::free($area, $date),
        ]);
    }

    public function contact(Request $request): JsonResponse
    {
        if ($this->isBot($request)) {
            return response()->json(['ok' => true, 'message' => self::RECEIVED], 202);
        }
        $input = $this->clean($request->only(['name', 'phone', 'email', 'matter', 'message']));
        $input['privacy'] = $request->boolean('privacy');
        $validator = Validator::make($input, [
            'name' => 'required|string|max:160',
            'phone' => 'nullable|string|max:60',
            'email' => 'required|email|max:180',
            'matter' => 'nullable|string|max:100',
            'message' => 'required|string|max:2500',
            'privacy' => 'accepted',
        ], $this->messages());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        $contact = ContactRequest::create([
            'nombre' => $input['name'], 'email' => strtolower($input['email']), 'telefono' => $input['phone'] ?? null,
            'servicio_interes' => ($input['matter'] ?? '') ?: 'Consulta general', 'mensaje' => $input['message'], 'estado' => 'nuevo',
        ]);
        $sent = $this->notify(fn () => Mail::to(config('site.email'))->send(new ContactReceived($contact)));

        return response()->json([
            'ok' => true, 'id' => sprintf('CON-%06d', $contact->id), 'message' => self::RECEIVED,
            'delivery' => $sent ? 'sent' : 'pending_configuration',
        ], 201);
    }

    public function reserve(Request $request): JsonResponse
    {
        if ($this->isBot($request)) {
            return response()->json(['ok' => true, 'message' => self::RECEIVED], 202);
        }
        $input = $this->clean($request->only(['service', 'mode', 'name', 'email', 'phone', 'date', 'time', 'notes', 'pedido', 'clave']));
        $input['privacy'] = $request->boolean('privacy');
        if (($input['mode'] ?? null) === 'online') {
            $input['mode'] = 'telematica';
        }
        $validator = Validator::make($input, [
            'service' => 'required|string|max:80',
            'mode' => 'required|in:presencial,telematica',
            'name' => 'required|string|max:160',
            'email' => 'nullable|email|max:180',
            'phone' => 'nullable|string|max:60',
            'date' => 'required|string',
            'time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'notes' => 'nullable|string|max:1500',
            'privacy' => 'accepted',
            'pedido' => 'nullable|string|max:40|required_with:clave',
            'clave' => 'nullable|string|max:80|required_with:pedido',
        ], $this->messages());
        $validator->after(function ($validator) use ($input) {
            if (empty($input['email']) && empty($input['phone'])) {
                $validator->errors()->add('email', 'Indica email o teléfono.');
            }
            if (! Availability::parseDate($input['date'] ?? '')) {
                $validator->errors()->add('date', 'Fecha no válida. Usa YYYY-MM-DD con una fecha real.');
            }
        });
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        $area = Catalog::area($input['service']);
        if (! $area || ! isset($area['modes'][$input['mode']])) {
            return response()->json(['ok' => false, 'error' => 'unknown_service_or_mode'], 400);
        }
        if (! in_array($input['time'], Availability::generated($area, $input['date']), true)) {
            return response()->json(['ok' => false, 'error' => 'slot_not_available'], 400);
        }

        $paid = ! empty($input['pedido']);
        $result = DB::transaction(function () use ($input, $area, $paid, $request) {
            if (! in_array($input['time'], Availability::free($area, $input['date'], lock: true), true)) {
                return ['error' => 'slot_occupied'];
            }
            if ($paid) {
                $used = BookingKeys::consume($input['pedido'], $input['clave'], "consulta-{$area['id']}-{$input['mode']}");
                if ($used !== true) {
                    return ['error' => $used === 'used' ? 'booking_key_used' : 'invalid_booking_key'];
                }
            }
            $service = Service::firstOrCreate(
                ['nombre' => $area['name']],
                ['descripcion' => $area['name'], 'precio_base' => $area['modes'][$input['mode']]['price_cents'] / 100, 'tipo' => 'con_cita', 'activo' => true],
            );

            return ['appointment' => Appointment::create([
                'client_id' => $request->user()?->client?->id,
                'guest_name' => $input['name'], 'guest_email' => ($input['email'] ?? '') ?: null, 'guest_phone' => ($input['phone'] ?? '') ?: null,
                'service_id' => $service->id, 'modalidad' => $input['mode'], 'duracion_minutos' => $area['duration_minutes'],
                'fecha' => $input['date'], 'hora' => $input['time'], 'estado' => 'pendiente',
                'notas' => ($input['notes'] ?? '') ?: null, 'order_id' => $paid ? $input['pedido'] : null,
            ])];
        });

        if (isset($result['error'])) {
            $messages = [
                'slot_occupied' => 'La franja seleccionada ya no está disponible.',
                'booking_key_used' => 'Esta clave de reserva ya se ha utilizado.',
                'invalid_booking_key' => 'La clave de reserva no es válida o ha caducado.',
            ];
            $status = $result['error'] === 'invalid_booking_key' ? 400 : 409;

            return response()->json(['ok' => false, 'error' => $result['error'], 'message' => $messages[$result['error']]], $status);
        }

        $appointment = $result['appointment'];
        $sent = $this->notify(function () use ($appointment) {
            Mail::to(config('site.email'))->send(new ReservationReceived($appointment, forOffice: true));
            if ($appointment->guest_email) {
                Mail::to($appointment->guest_email)->send(new ReservationReceived($appointment, forOffice: false));
            }
        });

        return response()->json([
            'ok' => true, 'id' => sprintf('CIT-%06d', $appointment->id),
            'message' => 'Solicitud de cita recibida. El despacho confirmará la disponibilidad y los siguientes pasos.',
            'delivery' => $sent ? 'sent' : 'pending_configuration', 'calendar' => 'pending_configuration',
            'payment' => $paid ? 'paid' : 'disabled',
        ], 201);
    }

    /** Los bots rellenan el campo trampa: se responde como si todo fuera bien y no se guarda nada. */
    private function isBot(Request $request): bool
    {
        return $request->filled('website');
    }

    /** Quita caracteres de control y espacios sobrantes (los textos se guardan en una sola línea). */
    private function clean(array $input): array
    {
        return array_map(
            fn ($value) => is_string($value) ? trim(preg_replace('/\s+/u', ' ', preg_replace('/\p{Cc}/u', ' ', $value))) : $value,
            $input
        );
    }

    private function invalid(array $errors): JsonResponse
    {
        $fields = [];
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $fields[] = ['field' => $field, 'message' => $message];
            }
        }

        return response()->json(['ok' => false, 'error' => 'validation_error', 'fields' => $fields], 400);
    }

    private function messages(): array
    {
        return [
            'required' => 'Falta un dato obligatorio (:attribute).',
            'required_with' => 'Indica el pedido y la clave de reserva.',
            'email' => 'Introduce un correo electrónico válido.',
            'privacy.accepted' => 'Debes aceptar la política de privacidad.',
            'max' => 'El texto de :attribute es demasiado largo.',
        ];
    }

    /** Un fallo de correo no debe perder la solicitud ya guardada. */
    private function notify(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (\Throwable $e) {
            logger()->warning('No se pudo enviar el aviso por correo: '.$e->getMessage());

            return false;
        }
    }
}
