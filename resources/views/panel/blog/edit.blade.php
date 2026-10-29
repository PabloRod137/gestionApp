<x-panel-layout>
    <div class="mb-6">
        <a href="{{ route('panel.blog.index') }}" class="inline-flex items-center space-x-1.5 text-xs text-gray-500 hover:text-gray-900 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Volver a la lista</span>
        </a>
    </div>

    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Modificar artículo</h1>
        <p class="text-sm text-gray-500">{{ $post->titulo }}</p>
    </div>

    @include('panel.partials.errors')

    <form action="{{ route('panel.blog.update', $post->id) }}" method="POST" enctype="multipart/form-data" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-6 max-w-4xl font-brand-sans">
        @csrf
        @method('PUT')
        @include('panel.blog._form', ['post' => $post])
        <button type="submit" class="w-full bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow">Guardar cambios</button>
    </form>
</x-panel-layout>
