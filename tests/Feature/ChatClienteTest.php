<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DocumentRequirement;
use App\Models\Expedient;
use App\Models\ExpedientDocument;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Asistente privado del Área Cliente: solo responde con los datos del cliente de la sesión (datos inventados). */
class ChatClienteTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/cliente/chat';

    private User $worker;
    private User $ana;
    private Client $clienteAna;
    private Client $clienteBeto;
    private Service $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->servicio = Service::create(['codigo' => 'S1', 'nombre' => 'Servicio de Prueba', 'precio_base' => 0, 'tipo' => 'sin_cita', 'genera_documento' => true]);
        $this->worker = User::factory()->create(['name' => 'Asesora Prueba']);
        $this->worker->assignRole('worker');
        [$this->ana, $this->clienteAna] = $this->cliente('Ana Ejemplo', 'C-ANA');
        [, $this->clienteBeto] = $this->cliente('Beto Ajeno', 'C-BETO');
    }

    /** @return array{0: User, 1: Client} */
    private function cliente(string $nombre, string $codigo): array
    {
        $u = User::factory()->create(['name' => $nombre]);
        $u->assignRole('client');
        $c = Client::create(['codigo' => $codigo, 'razon_social' => $nombre, 'tipo' => 'particular', 'user_id' => $u->id]);

        return [$u, $c];
    }

    private function expediente(Client $c, string $codigo, string $titulo, string $estado = 'en_proceso'): Expedient
    {
        return Expedient::create(['codigo' => $codigo, 'client_id' => $c->id, 'service_id' => $this->servicio->id, 'titulo' => $titulo, 'estado' => $estado, 'fecha_apertura' => '2026-09-01', 'worker_id' => $this->worker->id]);
    }

    private function say(array $body, ?User $como = null, ?array $state = null)
    {
        return $this->actingAs($como ?? $this->ana)->postJson(self::URL, $body + ['state' => $state]);
    }

    private function texto($respuesta): string
    {
        return json_encode($respuesta->json('messages'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function test_hay_que_iniciar_sesion_como_cliente(): void
    {
        $this->postJson(self::URL, ['text' => 'hola'])->assertUnauthorized();
        $this->actingAs($this->worker)->postJson(self::URL, ['text' => 'hola'])->assertForbidden();
    }

    public function test_el_saludo_usa_el_nombre_y_ofrece_el_menu_con_aviso_de_que_no_es_asesoramiento(): void
    {
        $r = $this->say(['text' => 'hola'])->assertOk();

        $this->assertStringContainsString('Hola, Ana Ejemplo', $r->json('messages.0.text'));
        $this->assertStringContainsString('No doy asesoramiento jurídico', $r->json('messages.0.text'));
        $etiquetas = array_column(end($r->json()['messages'])['buttons'], 'label');
        $this->assertContains('Mis expedientes', $etiquetas);
        $this->assertContains('Pedir un certificado', $etiquetas);
    }

    public function test_los_expedientes_son_solo_los_del_cliente_de_la_sesion(): void
    {
        $this->expediente($this->clienteAna, '2026-001', 'Herencia de Ana');
        $this->expediente($this->clienteBeto, '2026-002', 'Divorcio de Beto');

        $t = $this->texto($this->say(['text' => '¿Cómo van mis expedientes?']));

        $this->assertStringContainsString('2026-001', $t);
        $this->assertStringContainsString('Herencia de Ana', $t);
        $this->assertStringNotContainsString('2026-002', $t);
        $this->assertStringNotContainsString('Beto', $t);
    }

    public function test_pedir_el_detalle_de_un_expediente_ajeno_no_revela_nada(): void
    {
        $this->expediente($this->clienteAna, '2026-001', 'Herencia de Ana');
        $ajeno = $this->expediente($this->clienteBeto, '2026-002', 'Divorcio de Beto');

        $t = $this->texto($this->say(['action' => ['id' => 'expediente', 'payload' => (string) $ajeno->id]]));

        $this->assertStringNotContainsString('Divorcio', $t);
        $this->assertStringNotContainsString('2026-002', $t);
    }

    public function test_el_detalle_muestra_estado_asesor_y_documentos_sin_inventar_plazos(): void
    {
        $e = $this->expediente($this->clienteAna, '2026-001', 'Herencia de Ana', 'pendiente_cliente');

        $t = $this->texto($this->say(['action' => ['id' => 'expediente', 'payload' => (string) $e->id]]));

        $this->assertStringContainsString('Esperando documentación o respuesta tuya', $t);
        $this->assertStringContainsString('Tu asesor: Asesora Prueba', $t);
        $this->assertStringContainsString('Los plazos legales concretos los confirma tu asesor', $t);
    }

    public function test_un_id_de_cliente_enviado_en_la_peticion_se_ignora(): void
    {
        $this->expediente($this->clienteBeto, '2026-002', 'Divorcio de Beto');

        $t = $this->texto($this->say(['text' => 'mis expedientes', 'client_id' => $this->clienteBeto->id, 'user_id' => $this->clienteBeto->user_id]));

        $this->assertStringNotContainsString('2026-002', $t);
        $this->assertStringContainsString('Todavía no tienes expedientes', $t);
    }

    public function test_los_documentos_son_los_visibles_para_el_cliente_y_solo_se_enlaza_lo_descargable(): void
    {
        $e = $this->expediente($this->clienteAna, '2026-001', 'Herencia de Ana');
        $otro = $this->expediente($this->clienteBeto, '2026-002', 'Divorcio de Beto');
        $visible = ExpedientDocument::create(['expedient_id' => $e->id, 'client_id' => $this->clienteAna->id, 'nombre' => 'Escritura.pdf', 'ruta' => 'docs/escritura.pdf', 'visibilidad' => 'cliente']);
        ExpedientDocument::create(['expedient_id' => $e->id, 'client_id' => $this->clienteAna->id, 'nombre' => 'Nota interna.docx', 'ruta' => 'docs/nota.docx', 'visibilidad' => 'interno']);
        ExpedientDocument::create(['expedient_id' => $e->id, 'client_id' => $this->clienteAna->id, 'nombre' => 'Solo en SharePoint.pdf', 'visibilidad' => 'cliente']);
        ExpedientDocument::create(['expedient_id' => $otro->id, 'client_id' => $this->clienteBeto->id, 'nombre' => 'Sentencia de Beto.pdf', 'ruta' => 'docs/sentencia.pdf', 'visibilidad' => 'cliente']);

        $r = $this->say(['text' => 'mis documentos']);
        $t = $this->texto($r);

        $this->assertStringContainsString('Escritura.pdf', $t);
        $this->assertStringContainsString('/cliente/documentos/'.$visible->id, $t);
        $this->assertStringContainsString('Solo en SharePoint.pdf', $t);
        $this->assertStringNotContainsString('Nota interna', $t);
        $this->assertStringNotContainsString('Sentencia de Beto', $t);
        $enlaces = collect($r->json('messages.0.cards'))->whereNotNull('href');
        $this->assertCount(1, $enlaces);
    }

    public function test_las_facturas_pendientes_suman_solo_las_del_cliente(): void
    {
        $e = $this->expediente($this->clienteAna, '2026-001', 'Herencia de Ana');
        $otro = $this->expediente($this->clienteBeto, '2026-002', 'Divorcio de Beto');
        Invoice::create(['client_id' => $this->clienteAna->id, 'expedient_id' => $e->id, 'numero' => 'F-2026-001', 'fecha' => '2026-09-10', 'base_imponible' => 100, 'iva' => 21, 'total' => 121, 'estado' => 'pendiente', 'emision' => 'emitida']);
        Invoice::create(['client_id' => $this->clienteAna->id, 'expedient_id' => $e->id, 'numero' => 'F-2026-002', 'fecha' => '2026-09-11', 'base_imponible' => 50, 'iva' => 10.5, 'total' => 60.5, 'estado' => 'pagada', 'emision' => 'emitida']);
        Invoice::create(['client_id' => $this->clienteBeto->id, 'expedient_id' => $otro->id, 'numero' => 'F-2026-003', 'fecha' => '2026-09-12', 'base_imponible' => 900, 'iva' => 189, 'total' => 1089, 'estado' => 'pendiente', 'emision' => 'emitida']);

        $t = $this->texto($this->say(['text' => '¿Cuánto debo?']));

        $this->assertStringContainsString('F-2026-001', $t);
        $this->assertStringContainsString('121,00 €', $t);
        $this->assertStringNotContainsString('F-2026-002', $t);
        $this->assertStringNotContainsString('F-2026-003', $t);
        $this->assertStringNotContainsString('1.089', $t);
    }

    public function test_las_citas_son_las_proximas_del_cliente(): void
    {
        Appointment::create(['client_id' => $this->clienteAna->id, 'service_id' => $this->servicio->id, 'modalidad' => 'presencial', 'fecha' => now()->addDays(3)->toDateString(), 'hora' => '10:30:00', 'duracion_minutos' => 30, 'estado' => 'confirmada']);
        Appointment::create(['client_id' => $this->clienteAna->id, 'service_id' => $this->servicio->id, 'modalidad' => 'presencial', 'fecha' => now()->subDays(3)->toDateString(), 'hora' => '09:00:00', 'duracion_minutos' => 30, 'estado' => 'confirmada']);
        Appointment::create(['client_id' => $this->clienteBeto->id, 'service_id' => $this->servicio->id, 'modalidad' => 'presencial', 'fecha' => now()->addDays(2)->toDateString(), 'hora' => '17:45:00', 'duracion_minutos' => 30, 'estado' => 'confirmada']);

        $t = $this->texto($this->say(['text' => 'tengo alguna cita']));

        $this->assertStringContainsString('10:30', $t);
        $this->assertStringContainsString('Confirmada', $t);
        $this->assertStringNotContainsString('17:45', $t);
        $this->assertStringNotContainsString('09:00', $t);
    }

    public function test_lo_que_falta_aportar_sale_de_los_requisitos_del_servicio_y_avisa_si_el_despacho_espera_algo(): void
    {
        DocumentRequirement::create(['service_id' => $this->servicio->id, 'codigo' => 'R1', 'descripcion' => 'DNI de todos los herederos', 'obligatorio' => true, 'normalizado' => true, 'orden' => 1]);
        DocumentRequirement::create(['service_id' => $this->servicio->id, 'codigo' => 'R2', 'descripcion' => 'Certificado opcional', 'obligatorio' => false, 'normalizado' => true, 'orden' => 2]);
        $this->expediente($this->clienteAna, '2026-001', 'Herencia de Ana', 'pendiente_cliente');

        $t = $this->texto($this->say(['text' => '¿qué falta?']));

        $this->assertStringContainsString('DNI de todos los herederos', $t);
        $this->assertStringNotContainsString('Certificado opcional', $t);
        $this->assertStringContainsString('el despacho está esperando algo de ti en: 2026-001', $t);
    }

    public function test_pedir_un_certificado_con_un_solo_expediente_pregunta_cual_y_lo_deja_como_mensaje_al_asesor(): void
    {
        $e = $this->expediente($this->clienteAna, '2026-001', 'Comunidad de Ana');

        $paso1 = $this->say(['text' => 'necesito un certificado'])->assertOk();
        $this->assertSame(['flow' => 'certificado', 'step' => 'texto', 'expediente' => $e->id], $paso1->json('state'));
        $this->assertStringContainsString('Expediente 2026-001', $this->texto($paso1));

        $paso2 = $this->say(['text' => 'Certificado de estar al corriente de pago, para vender el piso'], null, $paso1->json('state'))->assertOk();

        $m = Message::firstOrFail();
        $this->assertSame($e->id, $m->expedient_id);
        $this->assertSame($this->ana->id, $m->from_user_id);
        $this->assertSame($this->worker->id, $m->to_user_id);
        $this->assertStringStartsWith('Solicitud de certificado', $m->contenido);
        $this->assertStringContainsString('para vender el piso', $m->contenido);
        $this->assertNull($paso2->json('state'));
        $this->assertStringContainsString('lo prepara y lo revisa una persona', $this->texto($paso2));
    }

    public function test_con_varios_expedientes_pregunta_sobre_cual_y_con_ninguno_remite_al_telefono(): void
    {
        $this->expediente($this->clienteAna, '2026-001', 'Uno');
        $b = $this->expediente($this->clienteAna, '2026-003', 'Dos');
        config(['site.phone' => '600 000 000']);

        $r = $this->say(['action' => ['id' => 'mensaje']]);
        $this->assertNull($r->json('state'));
        $botones = collect(end($r->json()['messages'])['buttons'])->where('id', 'elegir_mensaje');
        $this->assertCount(2, $botones);

        $elegido = $this->say(['action' => ['id' => 'elegir_mensaje', 'payload' => (string) $b->id]]);
        $this->assertSame($b->id, $elegido->json('state.expediente'));

        $this->actingAs($this->clienteBeto->user);
        $sin = $this->say(['action' => ['id' => 'mensaje']], $this->clienteBeto->user);
        $this->assertStringContainsString('600 000 000', $this->texto($sin));
        $this->assertNull($sin->json('state'));
    }

    public function test_un_estado_manipulado_con_el_expediente_de_otro_cliente_no_crea_ningun_mensaje(): void
    {
        $this->expediente($this->clienteAna, '2026-001', 'Uno');
        $ajeno = $this->expediente($this->clienteBeto, '2026-002', 'De Beto');

        $r = $this->say(['text' => 'hola, quiero esto'], null, ['flow' => 'mensaje', 'step' => 'texto', 'expediente' => $ajeno->id])->assertOk();

        $this->assertSame(0, Message::count());
        $this->assertNull($r->json('state'));
    }

    public function test_un_estado_con_forma_rara_se_descarta(): void
    {
        $this->expediente($this->clienteAna, '2026-001', 'Uno');

        $this->say(['text' => 'hola'], null, ['flow' => 'otro', 'step' => 'x', 'expediente' => 1])->assertOk();
        $this->say(['text' => 'quiero algo raro'], null, ['flow' => 'mensaje', 'step' => 'texto', 'expediente' => '1; drop'])->assertOk();

        $this->assertSame(0, Message::count());
    }

    public function test_lo_que_no_es_de_sus_gestiones_se_deriva_y_no_se_responde(): void
    {
        $t = $this->texto($this->say(['text' => '¿Quién ganará las elecciones?']));

        $this->assertStringContainsString('solo trato tus datos y gestiones', $t);
        $this->assertStringContainsString('Escribir al despacho', $t);
    }

    public function test_entradas_invalidas_dan_error_y_acciones_desconocidas_se_rechazan(): void
    {
        $this->say(['text' => ''])->assertStatus(400);
        $this->say(['text' => str_repeat('a', 501)])->assertStatus(400);
        $this->say(['action' => ['id' => 'borrar_todo']])->assertStatus(400);
        $this->say(['action' => ['id' => 'expediente', 'payload' => '1 OR 1=1']])->assertStatus(400);
    }

    public function test_la_auditoria_guarda_la_intencion_pero_no_lo_que_escribio_el_cliente(): void
    {
        $this->say(['text' => 'quiero ver mis facturas, mi secreto es hunter2']);

        $log = AuditLog::where('accion', 'chat_cliente')->firstOrFail();
        $this->assertSame($this->ana->id, $log->user_id);
        $this->assertSame('facturas', $log->datos_nuevos['intencion']);
        $this->assertStringNotContainsString('hunter2', json_encode($log->toArray()));
    }

    public function test_el_chat_tiene_limite_de_mensajes_por_minuto(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->say(['text' => 'hola'])->assertOk();
        }

        $this->say(['text' => 'hola'])->assertStatus(429);
    }
}
