<?php

namespace Tests\Feature;

use App\Mail\ContactReceived;
use App\Mail\ReservationReceived;
use App\Models\Appointment;
use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/nortia';

    /** Un día laborable futuro (al menos 7 días), para que ninguna prueba dependa de la hora actual. */
    private function businessDate(): string
    {
        $date = Carbon::now('Europe/Madrid')->addDays(7);
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date->toDateString();
    }

    private function reservation(array $overrides = []): array
    {
        return array_merge([
            'service' => 'legal', 'mode' => 'telematica', 'name' => 'María García', 'email' => 'maria@example.test',
            'phone' => '600000000', 'date' => $this->businessDate(), 'time' => '10:00', 'notes' => 'Duda sobre un contrato',
            'privacy' => true,
        ], $overrides);
    }

    public function test_los_servicios_se_publican_con_modalidades_y_precios_en_centimos(): void
    {
        $json = $this->getJson(self::BASE.'/services')->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame(['legal', 'fiscal', 'laboral', 'comunidades', 'inmobiliario'], array_column($json['services'], 'id'));
        $legal = $json['services'][0];
        $this->assertSame(45, $legal['durationMinutes']);
        $this->assertSame(8000, $legal['modes']['telematica']['priceCents']);
        $this->assertSame(9500, $legal['modes']['presencial']['priceCents']);
    }

    public function test_la_disponibilidad_ofrece_las_franjas_de_atencion_de_lunes_a_viernes(): void
    {
        $date = $this->businessDate();

        $json = $this->getJson(self::BASE."/availability?service=legal&date=$date")->assertOk()->json();

        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '16:00', '16:30', '17:00', '17:30'], $json['slots']);
        $this->assertSame(45, $json['durationMinutes']);
    }

    public function test_el_fin_de_semana_no_tiene_franjas(): void
    {
        $saturday = Carbon::now('Europe/Madrid')->addDays(14)->next(Carbon::SATURDAY)->toDateString();

        $this->getJson(self::BASE."/availability?service=legal&date=$saturday")->assertOk()->assertJsonPath('slots', []);
    }

    public function test_una_disponibilidad_con_datos_invalidos_da_400(): void
    {
        $this->getJson(self::BASE.'/availability?service=otro&date='.$this->businessDate())->assertStatus(400)->assertJsonPath('error', 'invalid_service_or_date');
        $this->getJson(self::BASE.'/availability?service=legal&date=2026-02-30')->assertStatus(400);
    }

    public function test_reservar_crea_la_cita_y_avisa_al_despacho_y_al_cliente(): void
    {
        Mail::fake();

        $this->postJson(self::BASE.'/reservations', $this->reservation())->assertCreated()->assertJsonPath('ok', true);

        $appointment = Appointment::firstOrFail();
        $this->assertSame('maria@example.test', $appointment->guest_email);
        $this->assertSame('telematica', $appointment->modalidad);
        $this->assertSame(45, $appointment->duracion_minutos);
        $this->assertSame('pendiente', $appointment->estado);
        $this->assertSame('Legal - consulta inicial', $appointment->service->nombre);
        Mail::assertSent(ReservationReceived::class, 2);
    }

    public function test_un_hueco_ocupado_ya_no_se_ofrece_y_no_se_puede_reservar_dos_veces(): void
    {
        Mail::fake();
        $date = $this->businessDate();
        $this->postJson(self::BASE.'/reservations', $this->reservation(['date' => $date, 'time' => '10:00']))->assertCreated();

        $slots = $this->getJson(self::BASE."/availability?service=legal&date=$date")->json('slots');
        // 09:30 (acabaría a las 10:15), 10:00 y 10:30 se solapan con la cita de 10:00 a 10:45
        $this->assertSame(['09:00', '11:00', '16:00', '16:30', '17:00', '17:30'], $slots);

        $this->postJson(self::BASE.'/reservations', $this->reservation(['date' => $date, 'time' => '10:30', 'email' => 'otra@example.test']))
            ->assertStatus(409)->assertJsonPath('error', 'slot_occupied');
        $this->assertSame(1, Appointment::count());
    }

    public function test_la_reserva_exige_consentimiento_y_un_medio_de_contacto(): void
    {
        $this->postJson(self::BASE.'/reservations', $this->reservation(['privacy' => false]))->assertStatus(400)->assertJsonPath('error', 'validation_error');
        $this->postJson(self::BASE.'/reservations', $this->reservation(['email' => '', 'phone' => '']))->assertStatus(400)->assertJsonPath('error', 'validation_error');
        $this->assertSame(0, Appointment::count());
    }

    public function test_una_franja_fuera_de_horario_se_rechaza(): void
    {
        $this->postJson(self::BASE.'/reservations', $this->reservation(['time' => '13:00']))->assertStatus(400)->assertJsonPath('error', 'slot_not_available');
        $this->postJson(self::BASE.'/reservations', $this->reservation(['time' => '10:15']))->assertStatus(400);
        $this->postJson(self::BASE.'/reservations', $this->reservation(['service' => 'inexistente']))->assertStatus(400);
        $this->assertSame(0, Appointment::count());
    }

    public function test_el_campo_trampa_de_los_bots_responde_bien_pero_no_guarda_nada(): void
    {
        Mail::fake();

        $this->postJson(self::BASE.'/reservations', $this->reservation(['website' => 'http://spam.test']))->assertStatus(202)->assertJsonPath('ok', true);
        $this->postJson(self::BASE.'/contact', ['website' => 'x', 'name' => 'Bot'])->assertStatus(202);

        $this->assertSame(0, Appointment::count());
        $this->assertSame(0, ContactRequest::count());
        Mail::assertNothingSent();
    }

    public function test_el_contacto_se_guarda_y_avisa_al_despacho(): void
    {
        Mail::fake();

        $this->postJson(self::BASE.'/contact', [
            'name' => 'Luis Pérez', 'email' => 'luis@example.test', 'phone' => '', 'matter' => 'Fiscal',
            'message' => 'Necesito revisar mi declaración.', 'privacy' => true,
        ])->assertCreated()->assertJsonPath('ok', true);

        $contact = ContactRequest::firstOrFail();
        $this->assertSame('Luis Pérez', $contact->nombre);
        $this->assertSame('Fiscal', $contact->servicio_interes);
        Mail::assertSent(ContactReceived::class, 1);
    }

    public function test_el_contacto_sin_consentimiento_o_con_datos_invalidos_da_400(): void
    {
        $base = ['name' => 'Luis', 'email' => 'luis@example.test', 'message' => 'Hola', 'privacy' => true];

        $this->postJson(self::BASE.'/contact', array_merge($base, ['privacy' => false]))->assertStatus(400);
        $this->postJson(self::BASE.'/contact', array_merge($base, ['email' => 'no-es-un-correo']))->assertStatus(400)
            ->assertJsonStructure(['fields' => [['field', 'message']]]);
        $this->assertSame(0, ContactRequest::count());
    }

    public function test_los_formularios_tienen_limite_de_peticiones(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(self::BASE.'/contact', ['website' => 'bot']);
        }

        $this->postJson(self::BASE.'/contact', ['website' => 'bot'])->assertStatus(429);
    }
}
