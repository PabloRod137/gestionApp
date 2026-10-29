<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar tareas automáticas de Nortia (se ejecutan cada minuto en segundo plano)
Schedule::command('mail:poll')->everyMinute();
Schedule::command('despacho:borradores')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('scans:poll')->everyMinute();
Schedule::command('despacho:exportar-estados')->everyFiveMinutes()->withoutOverlapping()->when(fn () => config('sync.activo'));
Schedule::command('verifactu:enviar')->everyMinute()->withoutOverlapping()->when(fn () => config('verifactu.activo'));
Schedule::command('facturas:fin-de-mes')->lastDayOfMonth('08:00')->when(fn () => config('facturacion.fin_de_mes_automatico'));
Schedule::command('documentos:inventariar')->everyTenMinutes()->withoutOverlapping()->when(fn () => config('documental.activo'));
Schedule::command('documentos:barrido')->dailyAt('02:30')->withoutOverlapping()->when(fn () => config('documental.activo'));
