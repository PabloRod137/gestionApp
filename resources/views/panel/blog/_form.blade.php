{{-- Campos del artículo del blog (los usan "crear" y "editar"); $post es null al crear. --}}
@php
    $field = 'w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright';
    $label = 'block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2';
@endphp

<div>
    <label class="{{ $label }}" for="titulo">Título del artículo *</label>
    <input type="text" id="titulo" name="titulo" value="{{ old('titulo', $post?->titulo) }}" class="{{ $field }}" placeholder="Ej. Nuevas deducciones fiscales en Cantabria 2026" required>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div>
        <label class="{{ $label }}" for="category_id">Categoría *</label>
        <select id="category_id" name="category_id" class="{{ $field }}" required>
            @foreach ($categorias as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $post?->category_id) == $cat->id)>{{ $cat->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="{{ $label }}" for="imagen">Imagen de portada (opcional)</label>
        <input type="file" id="imagen" name="imagen" accept="image/*" class="w-full text-xs text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-md file:border-0 file:text-[10px] file:font-semibold file:bg-[#A28C3F] file:text-[#1B1E21]">
        @if ($post?->imagen_portada)
            <p class="text-[10px] text-gray-400 mt-1">Ya tiene imagen; si subes otra, la sustituye.</p>
        @endif
    </div>
</div>

<div>
    <label class="{{ $label }}" for="excerpt">Resumen corto (aparece en los listados)</label>
    <textarea id="excerpt" name="excerpt" rows="2" maxlength="500" class="{{ $field }}" placeholder="Dos líneas sobre el tema…">{{ old('excerpt', $post?->excerpt) }}</textarea>
</div>

<div>
    <label class="{{ $label }}" for="contenido">Cuerpo del artículo *</label>
    <textarea id="contenido" name="contenido" rows="14" class="{{ $field }}" required>{{ old('contenido', $post?->contenido) }}</textarea>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <div>
        <label class="{{ $label }}" for="meta_title">Título para buscadores (opcional)</label>
        <input type="text" id="meta_title" name="meta_title" maxlength="255" value="{{ old('meta_title', $post?->meta_title) }}" class="{{ $field }}">
    </div>
    <div>
        <label class="{{ $label }}" for="meta_description">Descripción para buscadores (opcional)</label>
        <input type="text" id="meta_description" name="meta_description" maxlength="255" value="{{ old('meta_description', $post?->meta_description) }}" class="{{ $field }}">
    </div>
</div>

<div class="flex items-center space-x-3 bg-gray-50 p-4 rounded-xl border border-gray-100">
    <input type="checkbox" name="publicado" id="publicado" value="1" class="h-4 w-4 rounded border-gray-300" @checked(old('publicado', $post ? $post->publicado : true))>
    <label for="publicado" class="font-bold text-gray-900 uppercase tracking-wider">Publicado (si se desmarca, se guarda como borrador)</label>
</div>
