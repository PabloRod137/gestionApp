<?php

namespace Tests\Feature;

use App\Support\Readiness;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    private function completeLegalData(): void
    {
        config(['legal' => array_map(fn () => 'Dato real del despacho', config('legal'))]);
        config(['site.phone' => '942 000 000', 'site.email' => 'despacho@dominio-real.es']);
    }

    public function test_los_datos_de_contacto_de_la_empresa_anterior_bloquean_la_publicacion(): void
    {
        $this->completeLegalData();
        $this->assertSame([], Readiness::blockers());

        config(['site.phone' => '600 000 000', 'site.email' => 'info@nortia-demo.test']);
        $this->assertEqualsCanonicalizing(['site:SITE_PHONE', 'site:SITE_EMAIL'], array_keys(Readiness::blockers()));

        config(['site.phone' => '', 'site.email' => 'ejemplo@example.com']);
        $this->assertEqualsCanonicalizing(['site:SITE_PHONE', 'site:SITE_EMAIL'], array_keys(Readiness::blockers()));
    }

    public function test_los_datos_legales_de_relleno_bloquean_la_publicacion(): void
    {
        $blockers = Readiness::blockers();

        $this->assertArrayHasKey('legal:LEGAL_TAX_ID', $blockers);
        $this->assertArrayHasKey('legal:PRIVACY_CONTROLLER', $blockers);
        $this->assertCount(19, array_filter(array_keys($blockers), fn ($key) => str_starts_with($key, 'legal:')));
    }

    public function test_con_los_datos_completos_no_hay_bloqueos_en_desarrollo(): void
    {
        $this->completeLegalData();

        $this->assertSame([], Readiness::blockers());
    }

    public function test_los_marcadores_se_reconocen_como_relleno(): void
    {
        foreach (['', '  ', 'PENDIENTE', 'LEGAL_ADDRESS_REAL', 'Calle Ejemplo 1', 'TODO'] as $value) {
            $this->assertTrue(Readiness::isPlaceholder($value), "«{$value}» debería ser relleno");
        }
        foreach (['B-39123456', 'Calle de la Lealtad, 19 - 1º C, 39002 Santander', 'Colegio de Abogados de Cantabria'] as $value) {
            $this->assertFalse(Readiness::isPlaceholder($value), "«{$value}» no es relleno");
        }
    }

    public function test_en_produccion_se_exige_https_sin_depuracion_y_correo_real(): void
    {
        $this->completeLegalData();
        config(['app.env' => 'production', 'app.debug' => true, 'app.url' => 'http://nortia.com', 'mail.default' => 'log']);

        $this->assertEqualsCanonicalizing(['app:debug', 'app:url', 'mail:driver'], array_keys(Readiness::blockers()));
    }

    public function test_los_pagos_activos_exigen_claves_coherentes_con_el_entorno(): void
    {
        $this->completeLegalData();
        config(['shop.payments.enabled' => true, 'shop.payments.provider' => 'stripe', 'shop.payments.stripe' => ['secret_key' => '', 'webhook_secret' => '']]);
        $this->assertEqualsCanonicalizing(['payments:STRIPE_SECRET_KEY', 'payments:STRIPE_WEBHOOK_SECRET'], array_keys(Readiness::blockers()));

        config(['shop.payments.stripe' => ['secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_x'], 'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://nortia.com', 'mail.default' => 'smtp']);
        $this->assertSame(['payments:test_key_in_production'], array_keys(Readiness::blockers()));

        config(['shop.payments.stripe.secret_key' => 'sk_live_x', 'app.env' => 'local']);
        $this->assertSame(['payments:live_key_outside_production'], array_keys(Readiness::blockers()));
    }

    public function test_el_comando_falla_con_bloqueos_y_pasa_sin_ellos(): void
    {
        $this->artisan('site:readiness')->assertFailed();

        $this->completeLegalData();
        $this->artisan('site:readiness')->assertSuccessful();
    }
}
