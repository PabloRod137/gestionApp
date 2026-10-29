<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Corre el seeder.
     * Inserta los servicios oficiales que ofrece el despacho.
     */
    public function run(): void
    {
        $servicios = [
            [
                'nombre' => 'Compliance Empresarial',
                'descripcion' => 'Implementación y auditoría de planes de prevención de delitos penales en las organizaciones (Corporate Compliance), adaptados a la normativa UNE. Mitiga riesgos y asegura el cumplimiento ético y regulatorio de tu empresa.',
                'precio_base' => 1200.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Protección de Datos',
                'descripcion' => 'Adecuación completa al Reglamento General de Protección de Datos (RGPD). Registro de actividades de tratamiento, análisis de riesgos, redacción de cláusulas legales para web y empleados, y asesoramiento continuo.',
                'precio_base' => 350.00,
                'tipo' => 'sin_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Asesoría Fiscal y Declaración de la Renta',
                'descripcion' => 'Gestión de contabilidad empresarial, confección y presentación de declaraciones de impuestos trimestrales y anuales (IVA, IRPF, Sociedades), inspecciones fiscales y planificación fiscal corporativa y particular.',
                'precio_base' => 150.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Gestión Laboral y Nóminas',
                'descripcion' => 'Gestión laboral completa: redacción y registro de contratos, confección de nóminas, seguros sociales (TC1, TC2), altas y bajas en la Seguridad Social, despidos, convenios colectivos y conciliaciones.',
                'precio_base' => 120.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Consulta Jurídica',
                'descripcion' => 'Asesoramiento legal en redacción de contratos civiles y comerciales, herencias y sucesiones, constitución y disolución de sociedades mercantiles, reclamaciones de cantidad y defensa jurídica puntual.',
                'precio_base' => 200.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Registro de Marcas',
                'descripcion' => 'Protección del nombre comercial y marcas ante la Oficina Española de Patentes y Marcas (OEPM). Investigación previa de viabilidad, tramitación de expedientes legales, oposición y renovación.',
                'precio_base' => 180.00,
                'tipo' => 'sin_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Administración de Comunidades',
                'descripcion' => 'Gestión integral de comunidades de propietarios: contabilidad detallada, cobro de cuotas, reclamación de impagos judiciales, juntas ordinarias y extraordinarias, y coordinación de obras y reparaciones.',
                'precio_base' => 80.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Gestión Inmobiliaria y Compra Segura',
                'descripcion' => 'Asesoramiento legal e intermediación en compraventa y alquiler de inmuebles en Cantabria. Auditoría registral y catastral de fincas, redacción de contratos de arras y asistencia a firma notarial.',
                'precio_base' => 500.00,
                'tipo' => 'sin_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Gestión de Autónomos',
                'descripcion' => 'Servicio de asesoramiento y gestión integral para trabajadores autónomos en Cantabria: altas y bajas (RETA/AEAT), liquidación trimestral de IVA e IRPF, libros contables oficiales y consultas ilimitadas.',
                'precio_base' => 60.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Constitución de Empresas',
                'descripcion' => 'Gestión y asesoramiento para la creación de Sociedades Limitadas (S.L.) y otras formas jurídicas. Trámites notariales, obtención del CIF, alta en el registro mercantil y licencias de actividad en Cantabria.',
                'precio_base' => 350.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Herencias y Trámites Sucesorios',
                'descripcion' => 'Gestión integral de herencias y sucesiones: obtención de certificados (defunción, últimas voluntades), redacción de cuadernos particionales, liquidación del Impuesto de Sucesiones y registro de bienes.',
                'precio_base' => 250.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ],
            [
                'nombre' => 'Consulta de Asesoría',
                'descripcion' => 'Sesión de consultoría personalizada con uno de nuestros gestores administrativos o asesores fiscales y laborales colegiados para resolver dudas puntuales de tu negocio o trámites administrativos.',
                'precio_base' => 50.00,
                'tipo' => 'con_cita',
                'activo' => true,
            ]
        ];

        foreach ($servicios as $s) {
            Service::create($s);
        }
    }
}
