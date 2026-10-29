<x-client-layout>
    <!-- Cabecera -->
    <div class="mb-10 space-y-2">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">Mis Citas de Asesoramiento</h1>
        <p class="text-sm text-gray-500">Consulta tus reuniones confirmadas y solicita nuevas citas con tus asesores.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 text-xs">
        
        <!-- Lista de Citas Programadas -->
        <div class="lg:col-span-7 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-6">Historial y Agenda de Citas</h3>
            
            <div class="space-y-4">
                @forelse($citas as $cita)
                    <div class="border border-gray-100 p-4 rounded-xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 text-xs">
                        <div class="space-y-1">
                            <span class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px]">
                                {{ $cita->service->nombre ?? 'Consulta General' }}
                            </span>
                            <h4 class="font-bold text-gray-900">
                                Asesor asignado: {{ $cita->worker->name ?? 'Por asignar' }}
                            </h4>
                            <p class="text-gray-500 tabular-nums">
                                {{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }} a las {{ \Carbon\Carbon::parse($cita->hora)->format('H:i') }} hs
                            </p>
                            @if($cita->notas)
                                <p class="text-[10px] text-gray-400 italic font-normal mt-2">"{{ $cita->notas }}"</p>
                            @endif
                        </div>
                        
                        @php
                            $colores = [
                                'pendiente' => 'bg-amber-100 text-amber-950 border-amber-200',
                                'confirmada' => 'bg-blue-100 text-blue-900 border-blue-200',
                                'realizada' => 'bg-emerald-100 text-emerald-900 border-emerald-200',
                                'cancelada' => 'bg-red-100 text-red-900 border-red-200',
                            ];
                            $clase = $colores[$cita->estado] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <span class="border {{ $clase }} font-bold px-3 py-1 rounded-full uppercase tracking-wider text-[9px] text-center">
                            {{ $cita->estado }}
                        </span>
                    </div>
                @empty
                    <p class="text-gray-400 py-4">No tienes citas registradas en la agenda de tu cuenta.</p>
                @endforelse
            </div>
        </div>

        <!-- Formulario para Solicitar Nueva Cita -->
        <div class="lg:col-span-5">
            <div class="bg-forest-dark text-[#1B1E21] p-8 rounded-2xl shadow-xl border border-gold-warm/20 space-y-4">
                <h3 class="text-lg font-bold font-brand-serif">Solicitar Nueva Cita</h3>
                <p class="text-gray-300 text-xs leading-relaxed">
                    Indica la fecha y hora sugerida, selecciona el departamento o servicio que precisas y nos pondremos en contacto contigo telefónicamente para confirmar tu hueco en la agenda.
                </p>

                <form action="{{ route('cliente.citas.store') }}" method="POST" class="space-y-4">
                    @csrf
                    
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-2">Servicio Requerido *</label>
                        <select name="service_id" class="w-full bg-forest-medium border border-gray-700 rounded-lg px-4 py-2.5 text-gray-300 focus:ring-1 focus:ring-gold-bright" required>
                            @foreach($servicios as $s)
                                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-2">Fecha sugerida *</label>
                            <input type="date" name="fecha" class="w-full bg-forest-medium border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:ring-1 focus:ring-gold-bright" min="{{ date('Y-m-d') }}" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-2">Hora sugerida *</label>
                            <input type="time" name="hora" class="w-full bg-forest-medium border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:ring-1 focus:ring-gold-bright" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-2">Notas explicativas o tema a tratar</label>
                        <textarea name="notas" rows="3" class="w-full bg-forest-medium border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:ring-1 focus:ring-gold-bright" placeholder="Escribe aquí de qué trata la cita..."></textarea>
                    </div>

                    <button type="submit" class="w-full bg-gold-bright hover:bg-gold-warm text-emerald-950 font-bold py-3 rounded-lg text-xs uppercase tracking-wider transition">
                        Solicitar Confirmación
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-client-layout>
