<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\Expedient;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Appointment;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    use \Database\Seeders\Concerns\SoloDatosDeEjemplo;

    /**
     * Corre el seeder.
     * Genera registros de demostración en cascada para probar el portal de clientes y el panel de control.
     */
    public function run(): void
    {
        $this->exigirDatosDeEjemplo();

        // Obtener colecciones básicas para asociar relaciones
        $clients = Client::all();
        $services = Service::all();
        $workers = User::role('worker')->get();
        $categories = BlogCategory::all();

        if ($clients->isEmpty() || $services->isEmpty() || $workers->isEmpty() || $categories->isEmpty()) {
            return; // Evita fallos si no se corrieron seeders previos
        }

        // 1. Crear Configuraciones Globales (Settings)
        $settings = [
            ['clave' => 'company_name', 'valor' => 'Nortia (demostración)'],
            ['clave' => 'company_phone', 'valor' => '600 000 000'],
            ['clave' => 'company_email', 'valor' => 'demo@example.test'],
            ['clave' => 'company_address', 'valor' => 'Calle de ejemplo 1, Santander'],
            ['clave' => 'seo_meta_title', 'valor' => 'Nortia (demostración)'],
            ['clave' => 'seo_meta_description', 'valor' => 'Texto de demostración para buscadores.'],
            ['clave' => 'social_twitter', 'valor' => 'https://example.test/twitter'],
            ['clave' => 'social_linkedin', 'valor' => 'https://example.test/linkedin'],
        ];
        foreach ($settings as $set) {
            Setting::create($set);
        }

        // 2. Crear 10 Expedientes (Expedients)
        $expedientesData = [
            [
                'client_id' => $clients[0]->id, // Construcciones Cantabria
                'service_id' => $services[0]->id, // Compliance
                'worker_id' => $workers[1]->id, // María (Compliance)
                'titulo' => 'Programa Compliance Penal Cantabria 2026',
                'estado' => 'en_proceso',
                'fecha_apertura' => Carbon::now()->subDays(30),
                'notas_internas' => 'Auditoría interna de procesos inicial completada. Se está redactando la matriz de riesgos.',
            ],
            [
                'client_id' => $clients[0]->id, // Construcciones Cantabria
                'service_id' => $services[2]->id, // Fiscal
                'worker_id' => $workers[0]->id, // Francisco
                'titulo' => 'Liquidación Impuesto Sociedades Ejercicio 2025',
                'estado' => 'abierto',
                'fecha_apertura' => Carbon::now()->subDays(15),
                'notas_internas' => 'Pendiente recibir balances de sumas y saldos definitivos del tercer trimestre.',
            ],
            [
                'client_id' => $clients[1]->id, // Pescadería Gómez
                'service_id' => $services[3]->id, // Laboral
                'worker_id' => $workers[0]->id, // Francisco
                'titulo' => 'Alta Nuevos Contratos Temporales Campaña Verano',
                'estado' => 'finalizado',
                'fecha_apertura' => Carbon::now()->subDays(45),
                'fecha_cierre' => Carbon::now()->subDays(30),
                'notas_internas' => 'Trámites realizados en Seguridad Social. Contratos registrados en el SEPE.',
            ],
            [
                'client_id' => $clients[1]->id, // Pescadería Gómez
                'service_id' => $services[1]->id, // RGPD
                'worker_id' => $workers[1]->id, // María
                'titulo' => 'Adaptación RGPD Pescaderías Locales',
                'estado' => 'pendiente_cliente',
                'fecha_apertura' => Carbon::now()->subDays(10),
                'notas_internas' => 'Enviado borrador de cláusulas web. Pendiente de que el cliente firme el contrato de encargado de tratamiento.',
            ],
            [
                'client_id' => $clients[2]->id, // Carlos Carpintería
                'service_id' => $services[2]->id, // Fiscal
                'worker_id' => $workers[0]->id, // Francisco
                'titulo' => 'Declaración Renta 2025 - Régimen Estimación Directa Simplificada',
                'estado' => 'en_proceso',
                'fecha_apertura' => Carbon::now()->subDays(5),
                'notas_internas' => 'Recibido borrador y facturas emitidas/soportadas. Revisar deducciones autonómicas de Cantabria.',
            ],
            [
                'client_id' => $clients[3]->id, // Laura Díaz
                'service_id' => $services[4]->id, // Civil (Herencias)
                'worker_id' => $workers[1]->id, // María
                'titulo' => 'Tramitación de Herencia y Testamento Familiar Díaz-Muñoz',
                'estado' => 'en_proceso',
                'fecha_apertura' => Carbon::now()->subDays(20),
                'notas_internas' => 'Solicitado certificado de últimas voluntades y seguro de vida. Esperando respuesta registral.',
            ],
            [
                'client_id' => $clients[4]->id, // Comunidad Pereda 10
                'service_id' => $services[6]->id, // Comunidades
                'worker_id' => $workers[1]->id, // María
                'titulo' => 'Administración y Cuentas Anuales Comunidad Paseo Pereda 10',
                'estado' => 'abierto',
                'fecha_apertura' => Carbon::now()->subDays(60),
                'notas_internas' => 'Pendiente de convocar junta general extraordinaria para aprobar presupuesto de reparación de fachada.',
            ],
            [
                'client_id' => $clients[0]->id, // Construcciones Cantabria
                'service_id' => $services[5]->id, // Registro Marcas
                'worker_id' => $workers[1]->id, // María
                'titulo' => 'Registro Marca Comercial "Cantabria Hogar"',
                'estado' => 'finalizado',
                'fecha_apertura' => Carbon::now()->subDays(90),
                'fecha_cierre' => Carbon::now()->subDays(20),
                'notas_internas' => 'Publicado en el BOPI. Concesión definitiva de la marca sin oposiciones.',
            ],
            [
                'client_id' => $clients[3]->id, // Laura Díaz
                'service_id' => $services[7]->id, // Compra Segura
                'worker_id' => $workers[1]->id, // María
                'titulo' => 'Compra Segura - Chalet Unifamiliar Somo',
                'estado' => 'en_proceso',
                'fecha_apertura' => Carbon::now()->subDays(8),
                'notas_internas' => 'Revisada nota simple registral. Todo libre de cargas. Redactando contrato de arras confirmatorias.',
            ],
            [
                'client_id' => $clients[4]->id, // Comunidad Pereda 10
                'service_id' => $services[4]->id, // Civil
                'worker_id' => $workers[1]->id, // María
                'titulo' => 'Reclamación Judicial de Cuotas Comunidad - Propietario Moroso 4ºB',
                'estado' => 'en_proceso',
                'fecha_apertura' => Carbon::now()->subDays(12),
                'notas_internas' => 'Aprobada la liquidación de deuda en junta. Redactando demanda de juicio monitorio.',
            ]
        ];

        $expedientes = [];
        foreach ($expedientesData as $exp) {
            $expedientes[] = Expedient::create($exp);
        }

        // 3. Crear 5 Facturas (Invoices)
        // Factura 1: Pagada, asociada a Construcciones Cantabria (Expediente Compliance)
        $invoice1 = Invoice::create([
            'client_id' => $clients[0]->id,
            'expedient_id' => $expedientes[0]->id,
            'numero' => 'FAC-2026-0001',
            'fecha' => Carbon::now()->subDays(25),
            'base_imponible' => 1200.00,
            'iva' => 21.00,
            'total' => 1452.00,
            'estado' => 'pagada',
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'concepto' => 'Honorarios iniciales implantación Programa de Compliance Penal',
            'cantidad' => 1,
            'precio_unitario' => 1200.00,
            'subtotal' => 1200.00,
        ]);

        // Factura 2: Pendiente, asociada a Construcciones Cantabria (Expediente Marca)
        $invoice2 = Invoice::create([
            'client_id' => $clients[0]->id,
            'expedient_id' => $expedientes[7]->id,
            'numero' => 'FAC-2026-0002',
            'fecha' => Carbon::now()->subDays(5),
            'base_imponible' => 280.00,
            'iva' => 21.00,
            'total' => 338.80,
            'estado' => 'pendiente',
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice2->id,
            'concepto' => 'Tramitación de expediente OEPM y tasas de solicitud',
            'cantidad' => 1,
            'precio_unitario' => 180.00,
            'subtotal' => 180.00,
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice2->id,
            'concepto' => 'Honorarios profesionales gestión marcas',
            'cantidad' => 1,
            'precio_unitario' => 100.00,
            'subtotal' => 100.00,
        ]);

        // Factura 3: Pagada, asociada a Pescadería Gómez (Expediente Laboral)
        $invoice3 = Invoice::create([
            'client_id' => $clients[1]->id,
            'expedient_id' => $expedientes[2]->id,
            'numero' => 'FAC-2026-0003',
            'fecha' => Carbon::now()->subDays(20),
            'base_imponible' => 120.00,
            'iva' => 21.00,
            'total' => 145.20,
            'estado' => 'pagada',
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice3->id,
            'concepto' => 'Redacción contratos e inscripción Seguridad Social trabajadores estacionales',
            'cantidad' => 1,
            'precio_unitario' => 120.00,
            'subtotal' => 120.00,
        ]);

        // Factura 4: Pendiente, asociada a Laura Díaz (Expediente Herencia)
        $invoice4 = Invoice::create([
            'client_id' => $clients[3]->id,
            'expedient_id' => $expedientes[5]->id,
            'numero' => 'FAC-2026-0004',
            'fecha' => Carbon::now()->subDays(2),
            'base_imponible' => 300.00,
            'iva' => 21.00,
            'total' => 363.00,
            'estado' => 'pendiente',
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice4->id,
            'concepto' => 'Provision de fondos inicial estudios registrales de herencia',
            'cantidad' => 1,
            'precio_unitario' => 300.00,
            'subtotal' => 300.00,
        ]);

        // Factura 5: Cancelada, asociada a Comunidad Paseo Pereda 10 (Administración)
        $invoice5 = Invoice::create([
            'client_id' => $clients[4]->id,
            'expedient_id' => $expedientes[6]->id,
            'numero' => 'FAC-2026-0005',
            'fecha' => Carbon::now()->subDays(40),
            'base_imponible' => 80.00,
            'iva' => 21.00,
            'total' => 96.80,
            'estado' => 'cancelada',
        ]);
        InvoiceLine::create([
            'invoice_id' => $invoice5->id,
            'concepto' => 'Honorarios administración ordinaria mensual - anulada por error conceptual',
            'cantidad' => 1,
            'precio_unitario' => 80.00,
            'subtotal' => 80.00,
        ]);

        // Estas facturas de ejemplo son anteriores a Veri*Factu: solo lectura, sin registro de facturación (consulta directa
        // a la tabla: las facturas emitidas no se tocan desde el modelo). Para ver una factura Veri*Factu, emitir un borrador en el panel.
        Invoice::query()->toBase()->update(['emision' => Invoice::HISTORICA]);

        // 4. Crear 3 Citas (Appointments)
        // Cita 1: Pasada (Realizada)
        Appointment::create([
            'client_id' => $clients[0]->id,
            'service_id' => $services[0]->id,
            'worker_id' => $workers[1]->id, // María
            'fecha' => Carbon::now()->subDays(10),
            'hora' => '10:00:00',
            'estado' => 'realizada',
            'notas' => 'Cita inicial para revisar los estatutos sociales de la empresa.',
        ]);

        // Cita 2: Futura (Confirmada)
        Appointment::create([
            'client_id' => $clients[2]->id,
            'service_id' => $services[2]->id,
            'worker_id' => $workers[0]->id, // Francisco
            'fecha' => Carbon::now()->addDays(2),
            'hora' => '12:30:00',
            'estado' => 'confirmada',
            'notas' => 'Reunión presencial para validar facturas de gastos deducibles de cara al cierre trimestral.',
        ]);

        // Cita 3: Futura (Pendiente)
        Appointment::create([
            'client_id' => $clients[3]->id,
            'service_id' => $services[7]->id,
            'worker_id' => $workers[1]->id, // María
            'fecha' => Carbon::now()->addDays(5),
            'hora' => '09:30:00',
            'estado' => 'pendiente',
            'notas' => 'Reunión virtual para repasar las cláusulas del contrato de arras.',
        ]);

        // 5. Crear 2 Inmuebles en Cartelera (Properties)
        $prop1 = Property::create([
            'titulo' => 'Piso premium en primera línea con vistas al Sardinero',
            'descripcion' => 'Imponente piso señorial situado en la mejor zona residencial de Santander, frente a la playa del Sardinero. 180 m2 distribuidos en 4 dormitorios, 3 baños completos, gran salón comedor con terraza acristalada orientada al sur-este, cocina office y plaza de garaje doble en el mismo edificio. Excelentes calidades y estado impecable.',
            'precio' => 790000.00,
            'superficie' => 180.00,
            'localidad' => 'Santander',
            'tipo' => 'piso',
            'activo' => true,
            'coordenadas' => '43.47, -3.78',
        ]);
        PropertyImage::create([
            'property_id' => $prop1->id,
            'ruta' => 'properties/ejemplo_piso_1_principal.jpg',
            'orden' => 1,
        ]);
        PropertyImage::create([
            'property_id' => $prop1->id,
            'ruta' => 'properties/ejemplo_piso_1_salon.jpg',
            'orden' => 2,
        ]);

        $prop2 = Property::create([
            'titulo' => 'Amplio local comercial en el centro financiero de Santander',
            'descripcion' => 'Gran oportunidad comercial. Local esquinero con fachada de 15 metros a pie de calle en zona de mucho paso junto a la delegación de Hacienda. Dispone de planta baja comercial de 120 m2 diáfana y sótano de almacén de 80 m2. Salida de humos, escaparates blindados, persiana motorizada y aire acondicionado. Ideal para oficinas, clínica o comercio minorista.',
            'precio' => 320000.00,
            'superficie' => 200.00,
            'localidad' => 'Santander',
            'tipo' => 'local',
            'activo' => true,
            'coordenadas' => '43.46, -3.81',
        ]);
        PropertyImage::create([
            'property_id' => $prop2->id,
            'ruta' => 'properties/ejemplo_local_1.jpg',
            'orden' => 1,
        ]);

        // 6. Crear 3 Entradas de Blog (BlogPosts)
        BlogPost::create([
            'category_id' => $categories[0]->id, // Fiscal
            'titulo' => 'Principales novedades en la declaración de la Renta Cantabria 2026',
            'contenido' => '<p>Te explicamos de forma sencilla las nuevas deducciones aprobadas por el Gobierno de Cantabria para este ejercicio fiscal. Entre las más destacadas se encuentran el aumento del porcentaje de deducción por alquiler de vivienda para jóvenes y las nuevas desgravaciones para familias numerosas y contribuyentes con familiares dependientes a su cargo.</p><p>Es de vital importancia revisar el borrador suministrado por la Agencia Tributaria puesto que, de manera habitual, las deducciones autonómicas específicas no se aplican por defecto y han de ser cumplimentadas manualmente por un asesor experto.</p>',
            'excerpt' => 'Análisis experto sobre los cambios autonómicos y estatales más importantes de la Renta este año y cómo maximizar el ahorro fiscal en Cantabria.',
            'imagen_portada' => 'blog/renta_2026.jpg',
            'publicado' => true,
            'fecha_publicacion' => Carbon::now()->subDays(10),
            'meta_title' => 'Novedades Renta Cantabria 2026 - Guía Fiscal Nortia',
            'meta_description' => 'Descubre las nuevas deducciones fiscales de Cantabria en la Renta de este año: alquiler para jóvenes, dependientes y claves contables de ahorro.',
        ]);

        BlogPost::create([
            'category_id' => $categories[4]->id, // Compliance
            'titulo' => 'Por qué tu mediana empresa necesita un plan de Compliance Penal obligatorio',
            'contenido' => '<p>La responsabilidad penal corporativa ya no es solo cosa de grandes multinacionales cotizadas. Cada vez más, los tribunales españoles y la Fiscalía General del Estado inspeccionan a empresas familiares y PYMES tras detectarse delitos de estafa, fraude fiscal o contra los trabajadores en su directiva.</p><p>Un plan de prevención de delitos eficaz y homologado no solo protege penalmente a los administradores del negocio, sino que actúa como un aval de calidad reputacional indispensable para contratar con la administración pública o licitar grandes proyectos del sector privado.</p>',
            'excerpt' => 'El Compliance Penal se consolida como el mejor escudo legal y reputacional para proteger a las PYMES y a sus administradores frente a delitos penales de terceros.',
            'imagen_portada' => 'blog/compliance_pymes.jpg',
            'publicado' => true,
            'fecha_publicacion' => Carbon::now()->subDays(4),
            'meta_title' => 'Compliance Penal PYMES: ¿Es obligatorio? | Nortia',
            'meta_description' => 'Aprende los beneficios jurídicos de implementar un modelo de prevención de riesgos penales y cómo funciona la responsabilidad jurídica empresarial.',
        ]);

        BlogPost::create([
            'category_id' => $categories[3]->id, // Inmobiliario
            'titulo' => 'Consejos clave para la firma de un contrato de arras en compraventa de inmuebles',
            'contenido' => '<p>La firma del contrato de arras es el paso previo y más determinante al comprar o vender un inmueble. A menudo se le resta importancia por considerarse un mero formalismo técnico antes de ir a Notaría, pero un error en su redactado puede acarrear multas o la pérdida total de la fianza depositada.</p><p>Diferenciar correctamente entre arras confirmatorias, penales y penitenciales es vital. Nuestro equipo de asesoría inmobiliaria recomienda realizar siempre una auditoría registral completa de la finca para descartar embargos previos antes de realizar transferencia alguna.</p>',
            'excerpt' => 'Antes de adelantar dinero por tu futura casa, repasa los tipos de arras, penalizaciones y salvaguardias legales obligatorias para una compra segura.',
            'imagen_portada' => 'blog/contrato_arras.jpg',
            'publicado' => true,
            'fecha_publicacion' => Carbon::now()->subDays(1),
            'meta_title' => 'Tipos de Contratos de Arras Inmobiliarios | Nortia',
            'meta_description' => 'Analizamos las diferencias críticas entre arras confirmatorias y penitenciales en la compra de vivienda en Cantabria. Evita riesgos notariales.',
        ]);

        // 7. Crear Mensajes de ejemplo (Messages)
        // Mensaje de inicio de la conversación de parte del cliente
        $clientUser = User::where('email', 'cliente1@nortia.com')->first();
        $workerUser = $workers[1]; // María (Compliance)

        if ($clientUser && $workerUser) {
            Message::create([
                'expedient_id' => $expedientes[0]->id, // Expediente de compliance
                'from_user_id' => $clientUser->id,
                'to_user_id' => $workerUser->id,
                'contenido' => 'Hola María, te escribo para comentarte que ya tenemos listo el organigrama de la empresa que nos pediste para el mapa de riesgos. ¿Cómo te lo hago llegar?',
                'leido' => true,
            ]);

            // Respuesta del trabajador
            Message::create([
                'expedient_id' => $expedientes[0]->id,
                'from_user_id' => $workerUser->id,
                'to_user_id' => $clientUser->id,
                'contenido' => 'Hola, ¡excelente noticia! Puedes subirlo directamente aquí en el portal en el apartado de documentos del expediente. Así el resto del equipo de gestión también lo tendrá a mano de forma segura.',
                'leido' => true,
            ]);

            // Mensaje nuevo sin leer por el trabajador
            Message::create([
                'expedient_id' => $expedientes[0]->id,
                'from_user_id' => $clientUser->id,
                'to_user_id' => $workerUser->id,
                'contenido' => 'De acuerdo, acabo de subirlo en formato PDF bajo el nombre "Organigrama_ConsCantabria_2026.pdf". Échale un vistazo cuando puedas. ¡Gracias!',
                'leido' => false,
            ]);
        }
    }
}
