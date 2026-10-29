<x-panel-layout>
    <!-- Botón Volver -->
    <div class="mb-6">
        <a href="{{ route('panel.solicitudes.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a la bandeja</span>
        </a>
    </div>

    <!-- Título y Datos Rápidos -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
        <div class="space-y-1">
            <span class="text-[10px] text-gray-400">Solicitud #{{ $solicitud->id }}</span>
            <h1 class="text-xl font-bold text-gray-900 font-brand-serif leading-tight">{{ $solicitud->nombre }}</h1>
            <p class="text-gray-500 font-mono">Email: <strong class="text-gray-800 font-semibold">{{ $solicitud->email }}</strong> | Teléfono: <strong class="text-gray-800 font-semibold">{{ $solicitud->telefono ?? '—' }}</strong></p>
        </div>
        <div class="text-left sm:text-right">
            <p class="text-gray-400 font-mono">Fecha Recepción</p>
            <p class="font-bold text-gray-800 font-mono">{{ $solicitud->created_at->format('d/m/Y H:i') }} hs</p>
        </div>
    </div>

    <!-- Dos Columnas Detalle y Gestión -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 text-xs font-brand-sans">
        
        <!-- Detalle Mensaje -->
        <div class="lg:col-span-7 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm space-y-6">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 pb-3">Detalle de la Consulta</h3>
            
            <div class="space-y-2">
                <span class="text-gray-400 block font-semibold">Servicio o departamento de interés</span>
                <span class="bg-emerald-50 text-emerald-800 border border-emerald-100 font-bold px-2.5 py-1 rounded text-[10px] uppercase inline-block">
                    {{ $solicitud->servicio_interes ?? 'Consulta General' }}
                </span>
            </div>

            <div class="space-y-2">
                <span class="text-gray-400 block font-semibold">Mensaje original de la web</span>
                <div class="bg-gray-50 border border-gray-100 p-6 rounded-xl text-gray-700 leading-relaxed italic whitespace-pre-line text-xs font-normal">
                    "{{ $solicitud->mensaje }}"
                </div>
            </div>
        </div>

        <!-- Asignación y Estado -->
        <div class="lg:col-span-5">
            <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm space-y-6 sticky top-28">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 border-b border-gray-100 pb-3">Gestión y Seguimiento</h3>
                
                <!-- Estado del lead -->
                <form action="{{ route('panel.solicitudes.status', $solicitud->id) }}" method="POST" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <label class="block text-[10px] font-semibold text-gray-700 uppercase tracking-wider" for="estado">Estado de la solicitud *</label>
                    <select id="estado" name="estado" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-gold-bright" required>
                        <option value="nuevo" @selected($solicitud->estado === 'nuevo')>Nueva (pendiente de atender)</option>
                        <option value="gestionado" @selected($solicitud->estado === 'gestionado')>Gestionada</option>
                    </select>
                    <button type="submit" class="w-full bg-[#2B2F33] hover:bg-[#4F555A] text-white font-bold py-2.5 rounded-lg text-xs uppercase tracking-wider transition">Guardar estado</button>
                </form>

                <!-- Asesor responsable -->
                <form action="{{ route('panel.solicitudes.assign', $solicitud->id) }}" method="POST" class="space-y-3 border-t border-gray-100 pt-6">
                    @csrf
                    <label class="block text-[10px] font-semibold text-gray-700 uppercase tracking-wider" for="worker_id">Asesor responsable *</label>
                    <select id="worker_id" name="worker_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-gold-bright" required>
                        <option value="">Selecciona un asesor…</option>
                        @foreach ($asesores as $ase)
                            <option value="{{ $ase->id }}" @selected($solicitud->asignado_a == $ase->id)>{{ $ase->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-gray-400">Al asignarla, la solicitud pasa a «gestionada».</p>
                    <button type="submit" class="w-full bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold py-2.5 rounded-lg text-xs uppercase tracking-wider transition">Asignar asesor</button>
                </form>
            </div>
        </div>

    </div>
</x-panel-layout>
