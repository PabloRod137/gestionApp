<x-panel-layout>
    <div class="mb-6"><a href="{{ route('panel.comunidades.show', $comunidad->id) }}" class="text-xs text-gray-500 hover:text-gray-900">← Volver a {{ $comunidad->nombre }}</a></div>

    <div class="mb-6 space-y-1">
        <h1 class="text-2xl font-extrabold text-gray-900 font-brand-serif">Reporte mensual · {{ $comunidad->nombre }}</h1>
        <p class="text-sm text-gray-500 max-w-3xl">Escribe las cifras del mes y revisa los textos. El programa propone lo que consta registrado; tú lo confirmas. Con esto se rellena el modelo Word «Reporte mensual».</p>
    </div>

    @if (session('success')) <div class="mb-4 p-3 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 text-xs rounded-r-lg">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="mb-4 p-3 bg-rose-50 border-l-4 border-rose-500 text-rose-900 text-xs rounded-r-lg">{{ $errors->first() }}</div> @endif

    <form method="GET" action="{{ route('panel.comunidades.reporte', $comunidad->id) }}" class="mb-4 flex items-end gap-3 text-xs">
        <label class="block"><span class="block text-gray-400 uppercase text-[9px] mb-1">Periodo</span>
            <select name="periodo" onchange="this.form.submit()" class="border border-gray-200 rounded-lg px-3 py-2">
                @foreach ($periodos as $p => $etiqueta) <option value="{{ $p }}" {{ $p === $periodo ? 'selected' : '' }}>{{ ucfirst($etiqueta) }}</option> @endforeach
            </select>
        </label>
        <noscript><button class="border rounded-lg px-3 py-2">Ver</button></noscript>
    </form>

    <form method="POST" action="{{ route('panel.comunidades.reporte.guardar', $comunidad->id) }}" class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm text-xs space-y-5 max-w-4xl font-brand-sans">
        @csrf
        <input type="hidden" name="periodo" value="{{ $periodo }}">
        <h2 class="font-bold text-gray-900 uppercase tracking-wider text-[10px]">Resumen económico de {{ \App\Support\Comunidades\ReporteMensual::etiqueta($periodo) }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            @foreach (['ingresos' => 'Ingresos', 'gastos' => 'Gastos', 'saldo' => 'Saldo a cierre', 'cuotas_pendientes' => 'Cuotas pendientes'] as $campo => $etiqueta)
                @php $valor = old($campo, $reporte?->$campo !== null ? number_format((float) $reporte->$campo, 2, ',', '.') : ($campo === 'saldo' && $sugerencias['saldo_previsto'] !== null && ! $reporte ? '' : '')); @endphp
                <label class="block"><span class="block text-gray-500 font-semibold mb-1">{{ $etiqueta }} (€)</span>
                    <input type="text" name="{{ $campo }}" value="{{ $valor }}" inputmode="decimal" placeholder="0,00" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-right font-mono">
                </label>
            @endforeach
        </div>
        @if ($sugerencias['saldo_previsto'] !== null)
            <p class="text-[10px] text-gray-400">El saldo del reporte anterior fue {{ number_format($sugerencias['saldo_previsto'], 2, ',', '.') }} €; el saldo a cierre de este mes = ese saldo + ingresos − gastos, si no hay otros movimientos.</p>
        @endif

        <label class="block"><span class="block text-gray-500 font-semibold mb-1">Incidencias y actuaciones realizadas</span>
            <textarea name="incidencias" rows="5" class="w-full border border-gray-200 rounded-lg px-3 py-2">{{ old('incidencias', $reporte?->incidencias ?? $sugerencias['incidencias']) }}</textarea>
            @if (! $reporte && $sugerencias['incidencias']) <span class="text-[10px] text-gray-400">Propuesto por el programa a partir de lo registrado: revísalo antes de guardar.</span> @endif
        </label>
        <label class="block"><span class="block text-gray-500 font-semibold mb-1">Próximas actuaciones</span>
            <textarea name="proximas_actuaciones" rows="4" class="w-full border border-gray-200 rounded-lg px-3 py-2">{{ old('proximas_actuaciones', $reporte?->proximas_actuaciones ?? $sugerencias['proximas_actuaciones']) }}</textarea>
            @if (! $reporte && $sugerencias['proximas_actuaciones']) <span class="text-[10px] text-gray-400">Propuesto: citas y propuestas en curso. Revísalo antes de guardar.</span> @endif
        </label>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-2.5 rounded-lg uppercase tracking-wider text-[10px] transition">Guardar reporte</button>
        </div>
    </form>

    <div class="max-w-4xl mt-4 bg-white border border-gray-150 rounded-2xl p-5 shadow-sm text-xs font-brand-sans">
        <h2 class="font-bold text-gray-900 uppercase tracking-wider text-[10px] mb-2">Preparar el documento</h2>
        @if ($expediente && $reporte)
            <p class="text-gray-600 mb-3">Crea la propuesta del reporte de {{ \App\Support\Comunidades\ReporteMensual::etiqueta($periodo) }} en el expediente {{ $expediente->codigo }}. Después se aprueba y se genera el Word (con su PDF), y se revisa antes de usarlo.</p>
            <form method="POST" action="{{ route('panel.comunidades.reporte.preparar', $comunidad->id) }}">@csrf <input type="hidden" name="periodo" value="{{ $periodo }}">
                <button type="submit" class="border border-gray-300 hover:border-gray-500 font-bold px-4 py-2 rounded-lg">Preparar propuesta del reporte</button>
            </form>
        @elseif (! $expediente)
            <p class="text-gray-500">Esta comunidad no tiene un expediente de administración abierto (servicio «Administración comunidad mensual»).</p>
        @else
            <p class="text-gray-500">Guarda primero el reporte de este mes.</p>
        @endif
        <p class="mt-3 text-[10px] text-gray-400">El documento usa siempre el reporte del mes anterior al actual ({{ \App\Support\Comunidades\ReporteMensual::etiqueta(\App\Support\Comunidades\ReporteMensual::periodoPorDefecto()) }}). Lo que no esté guardado se pide al generar.</p>
    </div>

    @if ($historial->isNotEmpty())
        <div class="max-w-4xl mt-4 bg-white border border-gray-150 rounded-2xl p-5 shadow-sm text-xs font-brand-sans">
            <h2 class="font-bold text-gray-900 uppercase tracking-wider text-[10px] mb-2">Reportes anteriores</h2>
            <ul class="divide-y divide-gray-100">
                @foreach ($historial as $h)
                    <li class="py-1.5 flex justify-between"><a class="text-emerald-800 font-bold hover:underline" href="{{ route('panel.comunidades.reporte', [$comunidad->id, 'periodo' => $h->periodo]) }}">{{ ucfirst(\App\Support\Comunidades\ReporteMensual::etiqueta($h->periodo)) }}</a><span class="text-gray-500 font-mono">saldo {{ $h->saldo !== null ? number_format((float) $h->saldo, 2, ',', '.') : '—' }} €</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</x-panel-layout>
