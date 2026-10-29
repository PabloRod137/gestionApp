<x-client-layout>
    <!-- Botón volver -->
    <div class="mb-6">
        <a href="{{ route('cliente.expedientes.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a mis expedientes</span>
        </a>
    </div>

    <!-- Título y Encabezado -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white border border-gray-150 p-6 rounded-2xl shadow-sm mb-8 text-xs">
        <div class="space-y-1">
            <span class="text-[10px] tabular-nums text-gray-400">#EXP-{{ str_pad($expediente->id, 5, '0', STR_PAD_LEFT) }}</span>
            <h1 class="text-xl font-bold text-gray-900 font-brand-serif leading-tight">{{ $expediente->titulo }}</h1>
            <p class="text-gray-500 font-medium">Asesor asignado: <strong class="text-gray-900 font-semibold">{{ $expediente->worker->name ?? 'Por asignar' }}</strong></p>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="text-left sm:text-right">
                <p class="text-gray-400">Apertura del Asunto</p>
                <p class="font-bold text-gray-800 tabular-nums">{{ \Carbon\Carbon::parse($expediente->fecha_apertura)->format('d/m/Y') }}</p>
            </div>
            @if($expediente->fecha_cierre)
                <div class="border-l-0 sm:border-l border-gray-200 pl-0 sm:pl-3 text-left sm:text-right">
                    <p class="text-gray-400">Cierre del Asunto</p>
                    <p class="font-bold text-gray-800 tabular-nums">{{ \Carbon\Carbon::parse($expediente->fecha_cierre)->format('d/m/Y') }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- BARRA DE HITO / PROGRESO DEL EXPEDIENTE -->
    <div class="bg-white border border-gray-150 p-8 rounded-2xl shadow-sm mb-8">
        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-6">Estado del Trámite</h3>
        
        <div class="relative flex items-center justify-between">
            <!-- Línea de Conexión de Fondo -->
            <div class="absolute left-0 right-0 h-1 bg-gray-200 -z-10"></div>
            <!-- Línea de Progreso Activo -->
            @php
                $pasos = ['abierto', 'en_proceso', 'pendiente_cliente', 'finalizado'];
                $actualIndex = array_search($expediente->estado, $pasos);
                if ($actualIndex === false && $expediente->estado === 'cancelado') $actualIndex = -1;
                $progresoAncho = $actualIndex >= 0 ? ($actualIndex / (count($pasos) - 1)) * 100 : 0;
            @endphp
            <div class="absolute left-0 h-1 bg-emerald-600 -z-10 transition-all duration-500" style="width: {{ $progresoAncho }}%"></div>

            <!-- Paso 1: Abierto -->
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 0 ? 'bg-emerald-600 border-emerald-600 text-white shadow' : 'bg-white border-gray-300 text-gray-400' }}">1</div>
                <span class="text-[10px] mt-2 font-bold uppercase tracking-wider text-gray-500">Apertura</span>
            </div>

            <!-- Paso 2: En Proceso -->
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 1 ? 'bg-emerald-600 border-emerald-600 text-white shadow' : 'bg-white border-gray-300 text-gray-400' }}">2</div>
                <span class="text-[10px] mt-2 font-bold uppercase tracking-wider text-gray-500">Estudio / Trámite</span>
            </div>

            <!-- Paso 3: Pendiente Cliente -->
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 2 ? 'bg-emerald-600 border-emerald-600 text-white shadow' : 'bg-white border-gray-300 text-gray-400' }}">3</div>
                <span class="text-[10px] mt-2 font-bold uppercase tracking-wider text-gray-500">Espera Cliente</span>
            </div>

            <!-- Paso 4: Finalizado -->
            <div class="flex flex-col items-center">
                <div class="h-8 w-8 rounded-full border-2 flex items-center justify-center font-bold text-xs {{ $actualIndex >= 3 ? 'bg-emerald-600 border-emerald-600 text-white shadow' : 'bg-white border-gray-300 text-gray-400' }}">4</div>
                <span class="text-[10px] mt-2 font-bold uppercase tracking-wider text-gray-500">Cerrado</span>
            </div>
        </div>

        @if($expediente->estado === 'pendiente_cliente')
            <div class="mt-8 bg-amber-50 border border-amber-200 p-4 rounded-xl text-xs text-amber-900 flex items-start space-x-3">
                <svg class="h-5 w-5 text-amber-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <strong class="font-bold">Acción Requerida:</strong> Tus asesores están esperando que envíes o revises algún documento o realices alguna gestión detallada en el chat.
                </div>
            </div>
        @endif
    </div>

    <!-- DOS COLUMNAS: DOCUMENTOS Y CHAT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 text-xs">
        
        <!-- COL 1: DOCUMENTOS (Descarga de Archivos) -->
        <div class="lg:col-span-5 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-gray-900 font-brand-serif mb-6">Documentación Compartida</h3>
                
                <div class="space-y-4">
                    @forelse($documentos as $doc)
                        <div class="border border-gray-100 p-4 rounded-xl flex justify-between items-center hover:bg-gray-50/50 transition">
                            <div class="space-y-1">
                                <span class="font-bold text-gray-800 truncate max-w-[200px] block" title="{{ $doc->nombre }}">{{ $doc->nombre }}</span>
                                <span class="text-[10px] tabular-nums text-gray-400 block">{{ $doc->tipo_mime }}</span>
                                <span class="text-[9px] text-gray-400">Subido por: {{ $doc->uploader->name ?? 'Sistema' }}</span>
                            </div>
                            <a href="{{ route('cliente.documentos.download', $doc->id) }}" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold px-3 py-1.5 rounded-lg border border-emerald-100 transition flex items-center space-x-1.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span>Bajar</span>
                            </a>
                        </div>
                    @empty
                        <p class="text-gray-400 py-4">No se han compartido archivos todavía en este expediente.</p>
                    @endforelse
                </div>
            </div>

            <div class="mt-8 border-t border-gray-100 pt-6">
                <div class="bg-gray-50 p-4 rounded-xl text-[11px] text-gray-500 leading-relaxed">
                    Si necesitas adjuntar documentación adicional al expediente, puedes adjuntarla en el chat de la derecha o enviarla por correo electrónico a tu asesor de referencia.
                </div>
            </div>
        </div>

        <!-- COL 2: CHAT INTERNO (Conversaciones) -->
        <div class="lg:col-span-7 bg-white border border-gray-150 rounded-2xl p-6 shadow-sm flex flex-col justify-between h-[600px]">
            <!-- Título Chat -->
            <div class="border-b border-gray-100 pb-4 mb-4">
                <h3 class="text-base font-bold text-gray-900 font-brand-serif">Mensajería y Consultas Directas</h3>
                <p class="text-[10px] text-gray-400">Comunícate directamente con los asesores legales asignados a tu caso.</p>
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
                                <span>{{ $isOwn ? 'Tú' : $msg->sender->name }}</span>
                                <span class="tabular-nums">{{ $msg->created_at->format('d/m H:i') }}</span>
                            </div>
                            <p class="leading-relaxed whitespace-pre-line">{{ $msg->contenido }}</p>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12 text-gray-400">
                        <p class="font-medium">No hay mensajes en el historial.</p>
                        <p class="text-[10px] mt-1">Escribe tu primer mensaje abajo para iniciar la conversación.</p>
                    </div>
                @endforelse
            </div>

            <!-- Formulario de Envío de Mensaje -->
            <div class="border-t border-gray-100 pt-4">
                <form action="{{ route('cliente.mensajes.store', $expediente->id) }}" method="POST" class="flex gap-2 items-end">
                    @csrf
                    <div class="flex-grow">
                        <textarea name="contenido" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:ring-1 focus:ring-gold-bright focus:border-gold-bright placeholder-gray-400" placeholder="Escribe aquí tu duda o respuesta..." required></textarea>
                    </div>
                    <button type="submit" class="bg-forest-dark hover:bg-gold-warm hover:text-[#1B1E21] text-[#1B1E21] font-bold px-5 py-3 rounded-xl transition shadow flex-shrink-0">
                        Enviar
                    </button>
                </form>
            </div>
        </div>

    </div>

    <!-- Script de Autoscroll para el Chat al cargar -->
    <script>
        const chatBox = document.getElementById('chat-box');
        if(chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    </script>
</x-client-layout>
