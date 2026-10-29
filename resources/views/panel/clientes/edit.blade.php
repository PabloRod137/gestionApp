<x-panel-layout>
    <!-- Botón Volver -->
    <div class="mb-6">
        <a href="{{ route('panel.clientes.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver al listado</span>
        </a>
    </div>

    <!-- Título -->
    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Modificar Cliente: {{ $cliente->razon_social }}</h1>
        <p class="text-sm text-gray-500">Edita la información de filiación fiscal o datos de acceso del cliente.</p>
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

    <!-- Formulario de Edición -->
    <form action="{{ route('panel.clientes.update', $cliente->id) }}" method="POST" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-8 max-w-3xl">
        @csrf
        @method('PUT')

        <!-- Ficha Filiación -->
        <div class="space-y-6">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif border-b border-gray-100 pb-3">1. Ficha del Cliente</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">NIF / CIF / NIE *</label>
                    <input type="text" name="nif" value="{{ old('nif', $cliente->nif) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright uppercase" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Nombre o Razón Social *</label>
                    <input type="text" name="razon_social" value="{{ old('razon_social', $cliente->razon_social) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Tipo de Cliente *</label>
                    <select name="tipo" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                        <option value="particular" {{ old('tipo', $cliente->tipo) === 'particular' ? 'selected' : '' }}>Particular</option>
                        <option value="autónomo" {{ old('tipo', $cliente->tipo) === 'autónomo' ? 'selected' : '' }}>Autónomo</option>
                        <option value="empresa" {{ old('tipo', $cliente->tipo) === 'empresa' ? 'selected' : '' }}>Empresa / Sociedad</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Teléfono Móvil / Fijo</label>
                    <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Dirección Postal completa</label>
                <input type="text" name="direccion" value="{{ old('direccion', $cliente->direccion) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Notas Privadas / Observaciones internas</label>
                <textarea name="notas" rows="3" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">{{ old('notas', $cliente->notas) }}</textarea>
            </div>
        </div>

        <!-- Cuenta vinculada (Si tiene) -->
        @if($cliente->user)
            <div class="space-y-6 bg-gray-50/50 p-6 rounded-2xl border border-gray-100">
                <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider border-b border-gray-200 pb-3">2. Gestionar Cuenta de Acceso</h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Nombre del usuario portal</label>
                        <input type="text" name="name" value="{{ old('name', $cliente->user->name) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Correo electrónico del usuario</label>
                        <input type="email" name="email" value="{{ old('email', $cliente->user->email) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Cambiar contraseña (Dejar en blanco para conservar la actual)</label>
                    <input type="password" name="password" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
                </div>
            </div>
        @else
            <div class="bg-gray-50 p-6 rounded-2xl border border-gray-100 text-center">
                <p class="text-gray-400 mb-2">Este cliente no tiene una cuenta vinculada de acceso al portal.</p>
                <p class="text-[10px] text-gray-500">Para crearle una cuenta de acceso, puedes dar de alta un nuevo cliente y vincularla o contactar con tu administrador.</p>
            </div>
        @endif

        <!-- Botones de Envío -->
        <button type="submit" class="w-full bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Guardar Cambios
        </button>
    </form>
</x-panel-layout>
