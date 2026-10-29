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
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Registrar Nuevo Cliente</h1>
        <p class="text-sm text-gray-500">Crea una nueva ficha de cliente y opcionalmente sus claves de acceso al portal.</p>
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
    <form action="{{ route('panel.clientes.store') }}" method="POST" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-8 max-w-3xl">
        @csrf

        <!-- Bloque 1: Datos Filiación Cliente -->
        <div class="space-y-6">
            <h3 class="text-base font-bold text-gray-900 font-brand-serif border-b border-gray-100 pb-3">1. Ficha del Cliente</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">NIF / CIF / NIE *</label>
                    <input type="text" name="nif" value="{{ old('nif') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright placeholder-gray-400" placeholder="Ej. 12345678A" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Nombre o Razón Social *</label>
                    <input type="text" name="razon_social" value="{{ old('razon_social') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright placeholder-gray-400" placeholder="Ej. Juan Pérez o Lex S.L." required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Tipo de Cliente *</label>
                    <select name="tipo" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" required>
                        <option value="particular" {{ old('tipo') === 'particular' ? 'selected' : '' }}>Particular</option>
                        <option value="autónomo" {{ old('tipo') === 'autónomo' ? 'selected' : '' }}>Autónomo</option>
                        <option value="empresa" {{ old('tipo') === 'empresa' ? 'selected' : '' }}>Empresa / Sociedad</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Teléfono Móvil / Fijo</label>
                    <input type="text" name="telefono" value="{{ old('telefono') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Ej. 600 112 233">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Dirección Postal completa</label>
                <input type="text" name="direccion" value="{{ old('direccion') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Ej. Paseo de Pereda 14, 3º Dcha, Santander">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Notas Privadas / Observaciones internas</label>
                <textarea name="notas" rows="3" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Observaciones de interés, horarios de contacto, etc. (No visibles para el cliente)...">{{ old('notas') }}</textarea>
            </div>
        </div>

        <!-- Bloque 2: Cuenta de Acceso -->
        <div class="space-y-6 bg-gray-50/50 p-6 rounded-2xl border border-gray-100">
            <div class="flex items-center space-x-3">
                <input type="checkbox" name="crear_cuenta" id="crear_cuenta" value="1" class="h-4 w-4 rounded border-gray-300 text-forest-medium focus:ring-forest-medium" {{ old('crear_cuenta') ? 'checked' : '' }}>
                <label for="crear_cuenta" class="font-bold text-gray-900 uppercase tracking-wider">2. Crear cuenta de acceso al portal de cliente</label>
            </div>

            <!-- Campos Ocultos/Revelados dinámicamente -->
            <div id="cuenta-campos" class="{{ old('crear_cuenta') ? '' : 'hidden' }} space-y-6 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Nombre completo (Para saludo) *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="Ej. Juan Pérez">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Email del Cliente *</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" placeholder="correo@cliente.com">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">Contraseña inicial de acceso *</label>
                    <input type="text" name="password" id="password" value="{{ old('password', Str::random(10)) }}" class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright font-mono">
                    <p class="text-[10px] text-gray-400 mt-1">Sugerida por el sistema (puedes cambiarla). Apunta esta contraseña para entregársela al cliente.</p>
                </div>
            </div>
        </div>

        <!-- Botón de Envío -->
        <button type="submit" class="w-full bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Guardar y Crear Ficha
        </button>
    </form>

    <!-- Script de alternancia visual para los campos de cuenta -->
    <script>
        const chk = document.getElementById('crear_cuenta');
        const box = document.getElementById('cuenta-campos');
        const nameField = document.getElementById('name');
        const emailField = document.getElementById('email');

        chk.addEventListener('change', () => {
            if(chk.checked) {
                box.classList.remove('hidden');
                nameField.setAttribute('required', 'true');
                emailField.setAttribute('required', 'true');
            } else {
                box.classList.add('hidden');
                nameField.removeAttribute('required');
                emailField.removeAttribute('required');
            }
        });
    </script>
</x-panel-layout>
