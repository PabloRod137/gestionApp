<x-panel-layout>
    <!-- Botón Volver -->
    <div class="mb-6">
        <a href="{{ route('panel.expedientes.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a expedientes</span>
        </a>
    </div>

    <!-- Título y Datos de Ficha -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs font-brand-sans">
        <div class="space-y-1">
            <span class="text-[10px] font-mono text-gray-400">{{ $expediente->codigo ? 'Nº '.$expediente->codigo : '#EXP-'.str_pad($expediente->id, 5, '0', STR_PAD_LEFT) }}</span>
            <h1 class="text-xl font-bold text-gray-900 font-brand-serif leading-tight">{{ $expediente->titulo }}</h1>
            <p class="text-gray-500">Cliente: <a href="{{ route('panel.clientes.show', $expediente->client_id) }}" class="text-emerald-800 font-bold hover:underline">{{ $expediente->client->razon_social }}</a></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('panel.expedientes.edit', $expediente->id) }}" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-4 py-2 rounded-lg text-xs transition">
                Editar Expediente
            </a>
        </div>
    </div>

    <!-- Hitos de Progreso del Expediente -->
    <div class="bg-white border border-gray-150 p-8 rounded-2xl shadow-sm mb-8">
        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-6">Fase del Asunto</h3>
        
        <div class="relative flex items-center justify-between">
            <div class="absolute left-0 right-0 h-1 bg-gray-200 -z-10"></div>
            @php
                $pasos = ['abierto', 'en_proceso', 'pendiente_cliente', 'finalizado'];
                $actualIndex = array_search($expediente->estado, $pasos);
                if ($actualIndex === false && $expediente->estado === 'cancelado') $actualIndex = -1;
                $progresoAncho = $actualIndex >= 0 ? ($actualIndex / (count($pasos) - 1)) * 100 : 0;
            @endphp
            <div class="absolute left-0 h-1 bg-emerald-600 -z-10 transition-all duration-500" style="width: {{ $progresoAncho }}%"></div>

            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 0 ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300 text-gray-400' }}">1</div>
                <span class="text-[10px] mt-2 font-bold uppercase text-gray-500">Apertura</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 1 ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300 text-gray-400' }}">2</div>
                <span class="text-[10px] mt-2 font-bold uppercase text-gray-500">En Trámite</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 2 ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300 text-gray-400' }}">3</div>
                <span class="text-[10px] mt-2 font-bold uppercase text-gray-500">Espera Cliente</span>
            </div>
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 3 ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300 text-gray-400' }}">4</div>
                <span class="text-[10px] mt-2 font-bold uppercase text-gray-500">Cerrado</span>
            </div>
        </div>
    </div>

    @include('panel.expedientes.partials.situacion')
    @include('panel.expedientes.partials.actuaciones')
    @include('panel.expedientes.partials.propuesta', ['propuesta' => $propuesta])
    @include('panel.expedientes.partials.circuito')
    @include('panel.expedientes.partials.comunicacion')
    @include('panel.expedientes.partials.ia')

    <!-- DOS COLUMNAS: VAULT DE DOCUMENTOS Y CHAT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 text-xs font-brand-sans">
        
        <!-- Col 1: Repositorio Documentos (Con visibilidad interna / externa) -->
        <div class="lg:col-span-5 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-6">Documentación del Asunto</h3>
                
                <div class="space-y-4">
                    @forelse($documentos as $doc)
                        <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center">
                            <div class="space-y-1">
                                <span class="font-bold text-gray-800 truncate max-w-[180px] block" title="{{ $doc->nombre }}">{{ $doc->nombre }}</span>
                                <div class="flex items-center space-x-2">
                                    @if($doc->visibilidad === 'cliente')
                                        <span class="bg-emerald-50 text-emerald-800 border border-emerald-100 font-bold px-1.5 py-0.5 rounded text-[8px] uppercase">Visible Cliente</span>
                                    @else
                                        <span class="bg-gray-100 text-gray-800 border border-gray-200 font-bold px-1.5 py-0.5 rounded text-[8px] uppercase">Interno</span>
                                    @endif

                                    <!-- Clasificación por IA y Corrección Inline -->
                                    <div x-data="{ 
                                        editing: false, 
                                        categoria: '{{ $doc->categoria }}', 
                                        confianza: '{{ $doc->categoria_confianza }}',
                                        updateCategory(newCategory) {
                                            if (!newCategory) return;
                                            fetch('{{ route('panel.documentos.categoria', $doc->id) }}', {
                                                method: 'PATCH',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                    'Accept': 'application/json'
                                                },
                                                body: JSON.stringify({ categoria: newCategory })
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                if (data.success) {
                                                    this.categoria = newCategory;
                                                    this.confianza = 'alta';
                                                    this.editing = false;
                                                } else {
                                                    alert('Error al actualizar la categoría.');
                                                }
                                            })
                                            .catch(error => {
                                                console.error(error);
                                                alert('Error de conexión.');
                                            });
                                        }
                                    }" class="inline-block">
                                        <!-- Badge de Categoría -->
                                        <span 
                                            x-show="categoria && !editing"
                                            @click="editing = true"
                                            :class="{
                                                'bg-blue-50 text-blue-800 border-blue-100': categoria === 'factura',
                                                'bg-emerald-50 text-emerald-800 border-emerald-100': categoria === 'nomina',
                                                'bg-rose-50 text-rose-800 border-rose-100': categoria === 'notificacion_hacienda',
                                                'bg-amber-50 text-amber-800 border-amber-100': categoria === 'contrato',
                                                'bg-gray-150 text-gray-800 border-gray-200': categoria && categoria !== 'factura' && categoria !== 'nomina' && categoria !== 'notificacion_hacienda' && categoria !== 'contrato'
                                            }"
                                            class="inline-flex items-center space-x-1 border font-bold px-1.5 py-0.5 rounded text-[8px] uppercase cursor-pointer hover:opacity-80 transition"
                                            :title="'Categoría: ' + categoria.replace(/_/g, ' ') + ' (Confianza: ' + confianza + ')'"
                                        >
                                            <span x-text="categoria.replace(/_/g, ' ')"></span>
                                            <template x-if="confianza === 'baja'">
                                                <svg class="h-2.5 w-2.5 text-amber-500 ml-1 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="Confianza baja, verificar">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                </svg>
                                            </template>
                                        </span>

                                        <!-- Categorizar cuando es null -->
                                        <span 
                                            x-show="!categoria && !editing"
                                            @click="editing = true"
                                            class="bg-gray-50 text-gray-400 border border-gray-150 border-dashed font-semibold px-1.5 py-0.5 rounded text-[8px] uppercase cursor-pointer hover:bg-gray-100 hover:text-gray-600 transition"
                                        >
                                            + Categorizar
                                        </span>

                                        <!-- Select selector -->
                                        <div x-show="editing" class="inline-block" @click.away="editing = false">
                                            <select 
                                                x-model="categoria"
                                                @change="updateCategory($event.target.value)"
                                                class="border border-gray-200 rounded px-1 py-0.5 text-[8px] focus:ring-1 focus:ring-gold-bright uppercase font-bold text-gray-700 bg-white"
                                            >
                                                <option value="">Seleccionar...</option>
                                                <option value="nomina">Nómina</option>
                                                <option value="factura">Factura</option>
                                                <option value="contrato">Contrato</option>
                                                <option value="notificacion_hacienda">Notif. Hacienda</option>
                                                <option value="notificacion_seguridad_social">Notif. Seg. Social</option>
                                                <option value="declaracion_fiscal">Decl. Fiscal</option>
                                                <option value="escritura">Escritura</option>
                                                <option value="dni_nie">DNI / NIE</option>
                                                <option value="justificante_pago">Justificante Pago</option>
                                                <option value="correspondencia">Correspondencia</option>
                                                <option value="otro">Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-1">
                                <a href="{{ $doc->url_externa ?: route('panel.documentos.download', $doc->id) }}" @if($doc->url_externa) target="_blank" rel="noopener noreferrer" @endif class="bg-gray-50 hover:bg-gray-100 text-gray-700 font-bold p-1.5 rounded-lg border border-gray-200" title="Descargar">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                </a>
                                <form action="{{ route('panel.documentos.destroy', $doc->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas eliminar este documento?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold p-1.5 rounded-lg border border-rose-100" title="Eliminar">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-400 py-4">No se han aportado archivos todavía.</p>
                    @endforelse
                </div>
            </div>

            <!-- Formulario Subida Express -->
            <div class="mt-8 border-t border-gray-100 pt-6">
                <form action="{{ route('panel.documentos.store', $expediente->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Nombre del Archivo *</label>
                        <input type="text" name="nombre" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-gold-bright" placeholder="Ej. Contrato Firmado o Nómina Abril" required>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Seleccionar Archivo *</label>
                            <input type="file" name="archivo" class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-forest-dark file:text-white hover:file:bg-gold-warm hover:file:text-forest-dark" required>
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-2">Visibilidad Cliente *</label>
                            <select name="visibilidad" class="w-full border border-gray-200 rounded-lg px-3 py-2 focus:ring-1 focus:ring-gold-bright" required>
                                <option value="cliente">Visible en Portal</option>
                                <option value="interno">Solo Personal Interno</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold py-2.5 rounded-lg text-xs uppercase tracking-wider transition">
                        Subir Archivo
                    </button>
                </form>
            </div>
        </div>

        <!-- Col 2: Chat Conversaciones -->
        <div id="chat" class="lg:col-span-7 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between h-[650px]">
            <div class="border-b border-gray-100 pb-4 mb-4">
                <h3 class="text-base font-bold text-gray-900 font-brand-serif">Bitácora y Chat del Expediente</h3>
                <p class="text-[10px] text-gray-400">Intercambia mensajes directamente con el cliente. Todo queda auditado en el historial.</p>
            </div>

            <!-- Contenedor del Historial (Scrollable) -->
            <div class="flex-grow overflow-y-auto space-y-4 pr-2 mb-4" id="chat-box">
                @forelse($mensajes as $msg)
                    @php
                        $isOwn = $msg->from_user_id === Auth::id();
                    @endphp
                    <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[75%] rounded-2xl p-4 shadow-sm {{ $isOwn ? 'bg-forest-medium text-white rounded-br-none' : 'bg-gray-100 text-gray-800 rounded-bl-none' }}">
                            <div class="flex justify-between items-center space-x-6 text-[10px] opacity-75 mb-1.5 font-bold">
                                <span>{{ $msg->sender->name }}</span>
                                <span class="font-mono">{{ $msg->created_at->format('d/m H:i') }}</span>
                            </div>
                            <p class="leading-relaxed whitespace-pre-line">{{ $msg->contenido }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-gray-400">
                        <p class="font-medium">No se han registrado mensajes en el chat de este expediente.</p>
                    </div>
                @endforelse
            </div>

            <!-- Formulario Envío Mensaje -->
            <div class="border-t border-gray-100 pt-4">
                <form action="{{ route('panel.mensajes.store', $expediente->id) }}" method="POST" class="flex gap-2 items-end">
                    @csrf
                    <div class="flex-grow">
                        <textarea name="contenido" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:ring-1 focus:ring-gold-bright placeholder-gray-400" placeholder="Responder al cliente o añadir nota al expediente..." required></textarea>
                    </div>
                    <button type="submit" class="bg-forest-dark hover:bg-gold-warm hover:text-forest-dark text-white font-bold px-5 py-3 rounded-xl transition shadow flex-shrink-0">
                        Responder
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- Scroll automático -->
    <script>
        const chatBox = document.getElementById('chat-box');
        if(chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    </script>
</x-panel-layout>
