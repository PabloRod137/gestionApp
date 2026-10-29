<x-panel-layout>
    <!-- Encabezado y Acción -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10">
        <div class="space-y-1">
            <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif font-brand-sans">Gestión de Clientes</h1>
            <p class="text-sm text-gray-500">Administra las fichas de filiación y las cuentas de acceso al portal.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <!-- Importar Excel -->
            <form action="{{ route('panel.clientes.import') }}" method="POST" enctype="multipart/form-data" class="inline-block">
                @csrf
                <input type="file" name="archivo_excel" class="hidden" id="file-import-clientes" onchange="this.form.submit()">
                <button type="button" onclick="document.getElementById('file-import-clientes').click()" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-250 font-bold px-4 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow-sm inline-flex items-center space-x-1.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>Importar Excel</span>
                </button>
            </form>

            <!-- Exportar Excel -->
            <a href="{{ route('panel.clientes.export') }}" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold px-4 py-3 rounded-lg text-xs uppercase tracking-wider transition inline-flex items-center space-x-1.5 shadow-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Exportar Excel</span>
            </a>

            <!-- Nuevo Cliente -->
            <a href="{{ route('panel.clientes.create') }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow">
                Nuevo Cliente
            </a>
        </div>
    </div>

    <!-- Filtro / Buscador -->
    <div class="bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs">
        <form action="{{ route('panel.clientes.index') }}" method="GET" class="flex gap-4 items-center">
            <div class="flex-grow">
                <input type="text" name="search" value="{{ $buscar }}" placeholder="Buscar por Razón Social, NIF/CIF o correo electrónico..." class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright">
            </div>
            <button type="submit" class="bg-forest-medium hover:bg-forest-dark text-white font-bold px-6 py-2.5 rounded-lg text-xs uppercase transition">
                Buscar
            </button>
            @if($buscar)
                <a href="{{ route('panel.clientes.index') }}" class="text-gray-400 hover:text-gray-600 underline">Limpiar</a>
            @endif
        </form>
    </div>

    <!-- Listado en Tabla -->
    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider">
                        <th class="px-6 py-4">Razón Social</th>
                        <th class="px-6 py-4">NIF/CIF</th>
                        <th class="px-6 py-4">Tipo</th>
                        <th class="px-6 py-4">Teléfono</th>
                        <th class="px-6 py-4">Usuario Portal</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium font-mono">
                    @forelse($clientes as $cli)
                        <tr class="hover:bg-gray-50/50 transition">
                            <!-- Razon Social -->
                            <td class="px-6 py-4 font-bold text-gray-900 font-brand-sans">
                                <a href="{{ route('panel.clientes.show', $cli->id) }}" class="hover:underline">
                                    {{ $cli->razon_social }}
                                </a>
                            </td>

                            <!-- NIF -->
                            <td class="px-6 py-4 uppercase">
                                {{ $cli->nif }}
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-4 font-brand-sans">
                                <span class="bg-gray-100 text-gray-800 px-2 py-0.5 rounded text-[10px] uppercase font-bold">
                                    {{ $cli->tipo }}
                                </span>
                            </td>

                            <!-- Telefono -->
                            <td class="px-6 py-4">
                                {{ $cli->telefono ?? '—' }}
                            </td>

                            <!-- Cuenta vinculada -->
                            <td class="px-6 py-4 font-brand-sans text-xs">
                                @if($cli->user)
                                    <span class="text-emerald-800 font-semibold flex items-center space-x-1.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 block"></span>
                                        <span>{{ $cli->user->email }}</span>
                                    </span>
                                @else
                                    <span class="text-gray-400 font-normal">Sin cuenta asociada</span>
                                @endif
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4 text-right font-brand-sans flex items-center justify-end space-x-2">
                                <!-- Impersonar -->
                                @if($cli->user_id)
                                    <form method="POST" action="{{ route('panel.clientes.impersonate', $cli->id) }}" class="inline">@csrf<button type="submit" class="bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold px-3 py-1.5 rounded-lg text-[10px] transition" title="Iniciar sesión como este cliente en su portal">
                                        Suplantar
                                    </button></form>
                                @endif
                                
                                <a href="{{ route('panel.clientes.show', $cli->id) }}" class="text-gray-500 hover:text-gray-900 font-bold px-2 py-1">
                                    Ficha
                                </a>
                                
                                <a href="{{ route('panel.clientes.edit', $cli->id) }}" class="text-blue-600 hover:text-blue-900 font-bold px-2 py-1">
                                    Editar
                                </a>

                                <form action="{{ route('panel.clientes.destroy', $cli->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas dar de baja lógica a este cliente en el sistema?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-900 font-bold px-2 py-1">
                                        Baja
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr class="font-brand-sans">
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 font-medium">
                                No se encontraron clientes en el sistema.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div class="mt-6 text-xs font-semibold">
        {{ $clientes->appends(['search' => $buscar])->links() }}
    </div>
</x-panel-layout>
