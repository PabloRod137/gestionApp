<x-client-layout>
    <div class="mb-8 space-y-2">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-serif">Mis datos</h1>
        <p class="text-sm text-gray-500">Para preparar tus documentos necesitamos algunos datos. Completa los que faltan y los guardamos en tu ficha. Solo se rellenan los huecos: lo que ya consta no se cambia desde aquí.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm">
            @if($faltan)
                <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Datos que faltan</h2>
                <form method="POST" action="{{ route('cliente.datos.update') }}" class="space-y-4" novalidate>
                    @csrf
                    @foreach($faltan as $campo => $etiqueta)
                        <div>
                            <label for="dato-{{ $campo }}" class="block text-xs font-bold text-gray-700 mb-1">{{ $etiqueta }}</label>
                            <input id="dato-{{ $campo }}" name="{{ $campo }}" value="{{ old($campo) }}"
                                   type="{{ $campo === 'email' ? 'email' : ($campo === 'telefono' ? 'tel' : 'text') }}"
                                   autocomplete="{{ ['nif' => 'off', 'email' => 'email', 'telefono' => 'tel', 'direccion' => 'street-address'][$campo] }}"
                                   @error($campo) aria-invalid="true" aria-describedby="error-{{ $campo }}" @enderror
                                   class="w-full rounded-lg border {{ $errors->has($campo) ? 'border-red-400' : 'border-gray-300' }} px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#A28C3F]">
                            @error($campo)<p id="error-{{ $campo }}" class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <p class="text-[11px] text-gray-500">Puedes completar solo algunos y volver más tarde. Usamos estos datos únicamente para tus gestiones con el despacho, tal y como explica la <a href="/politica-de-privacidad" class="underline">política de privacidad</a>.</p>
                    <button class="bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold px-5 py-2.5 rounded-lg text-xs uppercase tracking-wider">Guardar mis datos</button>
                </form>
            @else
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">Tu ficha está completa. Gracias.</div>
            @endif
        </div>

        <div class="bg-white border border-gray-150 rounded-2xl p-6 shadow-sm text-sm">
            <h2 class="text-base font-bold text-gray-900 font-brand-serif mb-4">Lo que consta en tu ficha</h2>
            <dl class="space-y-3">
                <div><dt class="text-xs text-gray-400">Nombre o razón social</dt><dd class="font-medium text-gray-900">{{ $cliente->razon_social }}</dd></div>
                @foreach($etiquetas as $campo => $etiqueta)
                    @continue(isset($faltan[$campo]))
                    <div><dt class="text-xs text-gray-400">{{ $etiqueta }}</dt><dd class="font-medium text-gray-900 break-words">{{ $cliente->{$campo} }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-5 text-xs text-gray-500">¿Algún dato es incorrecto o ha cambiado? Escríbelo desde el asistente («Escribir a mi asesor») y se corrige con revisión del despacho.</p>
        </div>
    </div>
</x-client-layout>
