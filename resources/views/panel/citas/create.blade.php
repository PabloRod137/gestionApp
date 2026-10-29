<x-panel-layout>
    <!-- Botón Volver -->
    <div class="mb-6">
        <a href="{{ route('panel.citas.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a la agenda</span>
        </a>
    </div>

    <!-- Título -->
    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Registrar Nueva Cita</h1>
        <p class="text-sm text-gray-500">Agenda una reunión de asesoramiento presencial o virtual para un cliente.</p>
    </div>

    <!-- Errores de Validación -->
    @if ($errors->any())
        <div class="mb-8 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-lg text-rose-800 text-xs shadow-sm">
            <h4 class="font-bold mb-2">Por favor, corrige los siguientes errores:</h4>
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Formulario de Registro -->
    <form action="{{ route('panel.citas.store') }}" method="POST" x-data="{ clientType: '{{ old('client_type', 'registered') }}' }" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-6 max-w-3xl font-brand-sans">
        @csrf

        <!-- Tipo de Cliente -->
        <div class="flex items-center space-x-6 bg-stone-50 p-4 rounded-xl border border-stone-150 mb-4">
            <span class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">Tipo de Agenda:</span>
            <label class="inline-flex items-center space-x-2 cursor-pointer font-semibold text-gray-700">
                <input type="radio" name="client_type" value="registered" x-model="clientType" class="text-[#9c8d45] focus:ring-[#9c8d45] border-stone-300">
                <span>Cliente Registrado</span>
            </label>
            <label class="inline-flex items-center space-x-2 cursor-pointer font-semibold text-gray-700">
                <input type="radio" name="client_type" value="guest" x-model="clientType" class="text-[#9c8d45] focus:ring-[#9c8d45] border-stone-300">
                <span>Invitado / Prospecto</span>
            </label>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- Cliente dropdown (only if registered) -->
            <div x-show="clientType === 'registered'">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Cliente *</label>
                <select name="client_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" :required="clientType === 'registered'">
                    <option value="">Selecciona un cliente...</option>
                    @foreach($clientes as $cli)
                        <option value="{{ $cli->id }}" {{ old('client_id') == $cli->id ? 'selected' : '' }}>{{ $cli->razon_social }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Guest details (only if guest) -->
            <div x-show="clientType === 'guest'" class="grid grid-cols-1 sm:grid-cols-3 gap-4" style="display: none;">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Nombre del Invitado *</label>
                    <input type="text" name="guest_name" value="{{ old('guest_name') }}" :required="clientType === 'guest'"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Nombre completo">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Email del Invitado *</label>
                    <input type="email" name="guest_email" value="{{ old('guest_email') }}" :required="clientType === 'guest'"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="ejemplo@correo.com">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Teléfono del Invitado *</label>
                    <input type="text" name="guest_phone" value="{{ old('guest_phone') }}" :required="clientType === 'guest'"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="600000000">
                </div>
            </div>

            <!-- Asesor Asignado -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Asesor Responsable *</label>
                <select name="worker_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="">Selecciona un asesor...</option>
                    @foreach($asesores as $ase)
                        <option value="{{ $ase->id }}" {{ old('worker_id', Auth::id()) == $ase->id ? 'selected' : '' }}>{{ $ase->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Servicio Relacionado -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Servicio / Departamento *</label>
                <select name="service_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="">Selecciona un servicio...</option>
                    @foreach($servicios as $ser)
                        <option value="{{ $ser->id }}" {{ old('service_id') == $ser->id ? 'selected' : '' }}>{{ $ser->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Estado de la cita -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Estado Inicial *</label>
                <select name="estado" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="pendiente" {{ old('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente Confirmación</option>
                    <option value="confirmada" {{ old('estado') === 'confirmada' ? 'selected' : 'selected' }}>Confirmada</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Fecha -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Fecha Agendada *</label>
                <input type="date" name="fecha" value="{{ old('fecha', date('Y-m-d')) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
            </div>

            <!-- Hora -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Hora *</label>
                <input type="time" name="hora" value="{{ old('hora', '10:00') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
            </div>
        </div>

        <!-- Notas -->
        <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Notas de la Cita / Preparación</label>
            <textarea name="notas" rows="3" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Especificaciones sobre la reunión, documentación que el cliente debe aportar...">{{ old('notas') }}</textarea>
        </div>

        <!-- Botón de Envío -->
        <button type="submit" class="w-full bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Confirmar y Agendar Cita
        </button>
    </form>
</x-panel-layout>
