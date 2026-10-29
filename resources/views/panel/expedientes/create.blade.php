<x-panel-layout>
    <!-- Botón Volver -->
    <div class="mb-6">
        <a href="{{ route('panel.expedientes.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver al listado</span>
        </a>
    </div>

    <!-- Título -->
    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Registrar Nuevo Expediente</h1>
        <p class="text-sm text-gray-500">Abre una nueva carpeta de seguimiento para gestionar un trámite jurídico, fiscal, laboral o de comunidades.</p>
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
    <form action="{{ route('panel.expedientes.store') }}" method="POST" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-6 max-w-3xl">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Título / Asunto -->
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Título / Asunto del Expediente *</label>
                <input type="text" name="titulo" value="{{ old('titulo') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Ej. Declaración de Renta 2025 o Constitución S.L." required>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Cliente -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Cliente Asociado *</label>
                <select name="client_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="">Selecciona un cliente...</option>
                    @foreach($clientes as $cli)
                        <option value="{{ $cli->id }}" {{ old('client_id') == $cli->id ? 'selected' : '' }}>{{ $cli->razon_social }} ({{ $cli->nif }})</option>
                    @endforeach
                </select>
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
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Servicio / Tarifa Relacionada *</label>
                <select name="service_id" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="">Selecciona un servicio...</option>
                    @foreach($servicios as $ser)
                        <option value="{{ $ser->id }}" {{ old('service_id') == $ser->id ? 'selected' : '' }}>{{ $ser->nombre }} ({{ number_format($ser->precio_base, 0, ',', '.') }}€)</option>
                    @endforeach
                </select>
            </div>

            <!-- Estado Inicial -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Estado del Trámite *</label>
                <select name="estado" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    <option value="abierto" {{ old('estado') === 'abierto' ? 'selected' : '' }}>Abierto</option>
                    <option value="en_proceso" {{ old('estado') === 'en_proceso' ? 'selected' : '' }}>En Proceso</option>
                    <option value="pendiente_cliente" {{ old('estado') === 'pendiente_cliente' ? 'selected' : '' }}>Pendiente Cliente</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Fecha de Apertura -->
            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Fecha de Apertura *</label>
                <input type="date" name="fecha_apertura" value="{{ old('fecha_apertura', date('Y-m-d')) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
            </div>
        </div>

        <!-- Descripción -->
        <div>
            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Descripción detallada del trámite / Encargo</label>
            <textarea name="notas_internas" rows="4" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Escribe aquí las pretensiones del cliente, antecedentes, notas preliminares del caso...">{{ old('notas_internas') }}</textarea>
        </div>

        <!-- Botón de Envío -->
        <button type="submit" class="w-full bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Guardar y Abrir Expediente
        </button>
    </form>
</x-panel-layout>
