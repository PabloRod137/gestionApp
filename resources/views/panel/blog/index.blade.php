<x-panel-layout>
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-10">
        <div class="space-y-1">
            <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Artículos y actualidad jurídica</h1>
            <p class="text-sm text-gray-500">Publica novedades legislativas, circulares fiscales y noticias del despacho.</p>
        </div>
        <a href="{{ route('panel.blog.create') }}" class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-5 py-3 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Escribir artículo
        </a>
    </div>

    <div class="bg-white border border-gray-150 rounded-2xl overflow-hidden shadow-sm text-xs font-brand-sans">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-gray-400 font-bold uppercase tracking-wider text-[10px]">
                        <th class="px-6 py-4">Título</th>
                        <th class="px-6 py-4">Categoría</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4">Publicación</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-600 font-medium">
                    @forelse ($posts as $post)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4 font-bold text-gray-900">{{ $post->titulo }}</td>
                            <td class="px-6 py-4">
                                <span class="bg-emerald-50 text-emerald-800 font-bold px-2 py-0.5 rounded text-[10px] uppercase">{{ $post->category->nombre ?? 'Sin categoría' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                @if ($post->publicado)
                                    <span class="text-emerald-700 font-bold flex items-center space-x-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500 block"></span><span>Publicado</span></span>
                                @else
                                    <span class="text-gray-500 flex items-center space-x-1.5"><span class="h-2 w-2 rounded-full bg-gray-300 block"></span><span>Borrador</span></span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $post->fecha_publicacion?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    @if ($post->publicado)
                                        <a href="{{ route('site.blog.show', $post->slug) }}" target="_blank" class="text-gray-500 hover:text-gray-900 font-bold px-2 py-1">Ver en la web</a>
                                    @endif
                                    <a href="{{ route('panel.blog.edit', $post->id) }}" class="text-blue-600 hover:text-blue-900 font-bold px-2 py-1">Editar</a>
                                    <form action="{{ route('panel.blog.destroy', $post->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que quieres eliminar este artículo?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:text-rose-900 font-bold px-2 py-1">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-400 font-medium">Todavía no hay artículos en el blog.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 text-xs font-semibold">{{ $posts->links() }}</div>
</x-panel-layout>
