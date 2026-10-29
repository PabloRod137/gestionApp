<?php

namespace Tests\Feature;

use App\Mail\ContactReceived;
use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicChatTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/nortia/chat/public';

    private function say(array $body, ?array $state = null)
    {
        return $this->postJson(self::URL, $body + ['state' => $state]);
    }

    private function texts($response): string
    {
        return collect($response->json('messages'))->pluck('text')->implode(' ');
    }

    public function test_el_saludo_ofrece_el_menu_de_opciones(): void
    {
        $json = $this->say(['text' => 'hola'])->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertStringContainsString('asistente de Nortia', $json['messages'][0]['text']);
        $labels = array_column(end($json['messages'])['buttons'], 'label');
        $this->assertContains('Reservar cita', $labels);
        $this->assertContains('Que me contacte el despacho', $labels);
        $this->assertNull($json['state']);
    }

    public function test_los_precios_salen_del_catalogo_y_el_horario_y_telefono_de_la_configuracion(): void
    {
        config(['site.hours' => 'de martes a jueves', 'site.phone' => '600 123 456']);

        $this->assertStringContainsString('Legal: 80 € telemática, 95 € presencial', $this->texts($this->say(['text' => '¿Cuánto cuesta una consulta?'])));
        $this->assertStringContainsString('Comunidades: 50 € telemática, 65 € presencial', $this->texts($this->say(['text' => 'precios'])));
        $this->assertStringContainsString('de martes a jueves', $this->texts($this->say(['text' => 'horario'])));
        $this->assertStringContainsString('600 123 456', $this->texts($this->say(['action' => ['id' => 'contact']])));
    }

    public function test_gana_la_palabra_clave_mas_especifica_y_solo_palabras_completas(): void
    {
        // «area cliente» (más larga) gana a «documentos»/«servicio»
        $this->assertStringContainsString('Área Cliente es tu espacio privado', $this->texts($this->say(['text' => 'quiero acceder al area cliente'])));
        // «legalidad» no contiene la palabra completa «legal»
        $this->assertStringContainsString('No he entendido', $this->texts($this->say(['text' => 'legalidad'])));
        // acentos y mayúsculas no importan
        $this->assertStringContainsString('seis áreas', $this->texts($this->say(['text' => 'SERVICIOS!!'])));
    }

    public function test_los_enlaces_apuntan_a_las_paginas_de_la_web(): void
    {
        $json = $this->say(['action' => ['id' => 'booking']])->json();

        $this->assertSame('/reservar-cita', $json['messages'][0]['cards'][0]['href']);
    }

    public function test_lo_que_no_entiende_ofrece_el_menu_y_dejar_los_datos(): void
    {
        $json = $this->say(['text' => 'blablabla'])->json();

        $this->assertStringContainsString('No he entendido', $json['messages'][0]['text']);
        $this->assertContains('lead', array_column($json['messages'][0]['buttons'], 'id'));
    }

    public function test_toda_respuesta_termina_con_botones_para_no_dejar_al_visitante_sin_salida(): void
    {
        foreach (['hola', 'servicios', 'precios', 'reservar', 'horario', 'contacto', 'area cliente', 'xyz'] as $text) {
            $messages = $this->say(['text' => $text])->json('messages');
            $this->assertNotEmpty(end($messages)['buttons'], $text);
        }
    }

    public function test_el_flujo_de_contacto_guarda_los_datos_solo_tras_el_consentimiento(): void
    {
        Mail::fake();

        $state = $this->say(['action' => ['id' => 'lead']])->json('state');
        $this->assertSame('name', $state['step']);

        $state = $this->say(['text' => 'Ana López'], $state)->json('state');
        $this->assertSame('contact', $state['step']);

        $response = $this->say(['text' => 'ana@example.test'], $state)->assertOk();
        $state = $response->json('state');
        $this->assertSame('consent', $state['step']);
        $this->assertSame(0, ContactRequest::count(), 'Sin consentimiento no se guarda nada');
        $this->assertContains('lead_consent', array_column($response->json('messages.0.buttons'), 'id'));

        // escribir texto en lugar de pulsar el botón no cuenta como consentimiento
        $state = $this->say(['text' => 'sí, acepto'], $state)->json('state');
        $this->assertSame(0, ContactRequest::count());

        $done = $this->say(['action' => ['id' => 'lead_consent']], $state)->assertOk();
        $this->assertNull($done->json('state'));
        $this->assertStringContainsString('Referencia: CON-', $this->texts($done));
        $contact = ContactRequest::firstOrFail();
        $this->assertSame('Ana López', $contact->nombre);
        $this->assertSame('ana@example.test', $contact->email);
        Mail::assertSent(ContactReceived::class, 1);
    }

    public function test_el_contacto_acepta_un_telefono(): void
    {
        Mail::fake();
        $state = ['flow' => 'lead', 'step' => 'contact', 'data' => ['name' => 'Luis']];

        $state = $this->say(['text' => '+34 600 111 222'], $state)->json('state');
        $this->say(['action' => ['id' => 'lead_consent']], $state)->assertOk();

        $this->assertSame('+34 600 111 222', ContactRequest::firstOrFail()->telefono);
    }

    public function test_un_nombre_o_contacto_invalidos_se_vuelven_a_pedir(): void
    {
        $retry = $this->say(['text' => 'A'], ['flow' => 'lead', 'step' => 'name', 'data' => []]);
        $this->assertSame('name', $retry->json('state.step'));
        $this->assertStringContainsString('entre 2 y 100', $this->texts($retry));

        $retry = $this->say(['text' => 'no es un dato'], ['flow' => 'lead', 'step' => 'contact', 'data' => ['name' => 'Ana']]);
        $this->assertSame('contact', $retry->json('state.step'));
        $this->assertStringContainsString('teléfono', $this->texts($retry));
    }

    public function test_un_estado_manipulado_no_permite_guardar_datos_invalidos(): void
    {
        // el navegador guarda el estado: se vuelve a validar entero antes de usarlo
        $forged = ['flow' => 'lead', 'step' => 'consent', 'data' => ['name' => '<script>alert(1)</script>', 'email' => 'no-es-correo']];

        $response = $this->say(['action' => ['id' => 'lead_consent']], $forged)->assertOk();

        $this->assertSame(0, ContactRequest::count());
        $this->assertSame('name', $response->json('state.step'));
    }

    public function test_cancelar_vuelve_al_menu_y_un_estado_enorme_se_ignora(): void
    {
        $cancelled = $this->say(['action' => ['id' => 'cancel']], ['flow' => 'lead', 'step' => 'name', 'data' => []])->json();
        $this->assertNull($cancelled['state']);

        $huge = ['flow' => 'lead', 'step' => 'name', 'data' => ['name' => str_repeat('x', 5000)]];
        $this->assertNull($this->say(['text' => 'hola'], $huge)->json('state'));
    }

    public function test_las_entradas_invalidas_dan_400_con_un_mensaje_claro(): void
    {
        $this->postJson(self::URL, [])->assertStatus(400)->assertJsonPath('error', 'invalid_input');
        $this->say(['text' => str_repeat('a', 501)])->assertStatus(400);
        $this->say(['text' => '   '])->assertStatus(400);
        $this->say(['text' => ['no es texto']])->assertStatus(400);
        $this->say(['action' => ['id' => '../etc/passwd']])->assertStatus(400);
        $this->say(['action' => ['id' => 'menu', 'payload' => str_repeat('x', 201)]])->assertStatus(400);
    }

    public function test_el_chat_tiene_limite_de_peticiones(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->say(['text' => 'hola']);
        }

        $this->say(['text' => 'hola'])->assertStatus(429)->assertJsonPath('error', 'rate_limited');
    }
}
