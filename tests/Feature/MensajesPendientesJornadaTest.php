<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expedient;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Mensajes de clientes" en Mi jornada: a quién hay que responder, quién lleva más tiempo esperando. */
class MensajesPendientesJornadaTest extends TestCase
{
    use RefreshDatabase;

    private User $worker;
    private User $cliente;
    private Service $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->worker = User::factory()->create(['name' => 'Trabajadora Prueba']);
        $this->worker->assignRole('worker');
        $this->cliente = User::factory()->create(['name' => 'Usuario Cliente']);
        $this->cliente->assignRole('client');
        $this->servicio = Service::create(['codigo' => 'S', 'nombre' => 'Servicio', 'precio_base' => 0, 'tipo' => 'sin_cita']);
    }

    private function expediente(string $codigo, string $razon): Expedient
    {
        $c = Client::create(['codigo' => 'C-'.$codigo, 'razon_social' => $razon, 'tipo' => 'empresa']);

        return Expedient::create(['codigo' => $codigo, 'client_id' => $c->id, 'service_id' => $this->servicio->id, 'titulo' => 'Asunto '.$codigo, 'estado' => 'abierto']);
    }

    private function mensaje(Expedient $e, User $de, string $texto, string $hace = '0 minutes'): Message
    {
        $m = Message::create(['expedient_id' => $e->id, 'from_user_id' => $de->id, 'to_user_id' => $de->is($this->worker) ? $this->cliente->id : $this->worker->id, 'contenido' => $texto, 'leido' => false]);
        $m->forceFill(['created_at' => now()->sub($hace)])->saveQuietly();

        return $m;
    }

    private function jornada()
    {
        return $this->actingAs($this->worker)->get('/panel/dashboard')->assertOk();
    }

    public function test_solo_aparecen_las_conversaciones_cuyo_ultimo_mensaje_es_del_cliente(): void
    {
        $espera = $this->expediente('2026-001', 'Cliente Que Espera SL');
        $this->mensaje($espera, $this->cliente, '¿Tenéis ya mi certificado?', '3 hours');
        $respondida = $this->expediente('2026-002', 'Cliente Respondido SL');
        $this->mensaje($respondida, $this->cliente, 'Una duda', '5 hours');
        $this->mensaje($respondida, $this->worker, 'Resuelta, gracias', '4 hours');

        $this->jornada()->assertSee('Mensajes de clientes')->assertSee('Cliente Que Espera SL')->assertSee('¿Tenéis ya mi certificado?')
            ->assertDontSee('Cliente Respondido SL');
    }

    public function test_el_que_lleva_mas_tiempo_esperando_va_primero_y_se_cuenta_cuantos_hay(): void
    {
        $nuevo = $this->expediente('2026-001', 'Zeta Reciente SL');
        $this->mensaje($nuevo, $this->cliente, 'Hola', '10 minutes');
        $viejo = $this->expediente('2026-002', 'Alfa Antiguo SL');
        $this->mensaje($viejo, $this->cliente, 'Hola', '2 days');

        $this->jornada()->assertSeeInOrder(['Alfa Antiguo SL', 'Zeta Reciente SL'])->assertSee('2 por responder');
    }

    public function test_cada_fila_lleva_directo_al_chat_del_expediente_y_marca_la_urgencia(): void
    {
        $e = $this->expediente('2026-001', 'Cliente Uno SL');
        $this->mensaje($e, $this->cliente, 'Necesito respuesta', '3 days');

        $this->jornada()->assertSee("/panel/expedientes/{$e->id}#chat", false)->assertSee('hace 3 días')->assertSee('data-urgencia="alta"', false);
    }

    public function test_un_mensaje_largo_se_recorta_y_nunca_se_pinta_html(): void
    {
        $e = $this->expediente('2026-001', 'Cliente Uno SL');
        $this->mensaje($e, $this->cliente, '<script>alert(1)</script> '.str_repeat('palabra ', 60));

        $r = $this->jornada();
        $r->assertDontSee('<script>alert(1)</script>', false);
        $r->assertSee('…');
    }

    public function test_si_hay_mas_de_seis_se_enseñan_seis_y_se_avisa_del_resto(): void
    {
        foreach (range(1, 8) as $i) {
            $this->mensaje($this->expediente("2026-10$i", "Cliente $i SL"), $this->cliente, "Mensaje $i", "$i hours");
        }

        $this->jornada()->assertSee('8 por responder')->assertSee('y 2 más');
    }

    public function test_sin_pendientes_la_caja_es_una_sola_linea_tranquila(): void
    {
        $e = $this->expediente('2026-001', 'Cliente Uno SL');
        $this->mensaje($e, $this->cliente, 'Pregunta', '2 days');
        $this->mensaje($e, $this->worker, 'Respuesta', '1 days');

        $this->jornada()->assertSee('Todo respondido')->assertDontSee('por responder');
    }

    public function test_sin_ningun_mensaje_tambien_se_explica(): void
    {
        $this->jornada()->assertSee('Mensajes de clientes')->assertSee('Todo respondido');
    }

    public function test_la_caja_ya_no_se_llama_actividad_de_chats(): void
    {
        $this->jornada()->assertDontSee('Actividad de Chats');
    }

    public function test_el_chat_del_expediente_tiene_el_ancla_a_la_que_enlaza_la_jornada(): void
    {
        $e = $this->expediente('2026-001', 'Cliente Uno SL');

        $this->actingAs($this->worker)->get("/panel/expedientes/{$e->id}")->assertOk()->assertSee('id="chat"', false);
    }
}
