<x-panel-layout>
    <div class="mb-10 space-y-1">
        <h1 class="text-3xl font-extrabold text-gray-900 font-brand-sans">Configuración del sistema</h1>
        <p class="text-sm text-gray-500">Datos corporativos del despacho, información de contacto y textos para buscadores.</p>
    </div>

    @include('panel.partials.errors')

    <form action="{{ route('panel.configuracion.update') }}" method="POST" class="bg-white border border-gray-150 rounded-2xl p-8 shadow-sm text-xs space-y-8 max-w-2xl font-brand-sans">
        @csrf
        @method('PUT')

        @php
            $groups = [
                'Información del despacho' => [
                    'company_name' => ['Nombre comercial *', 'text'],
                    'company_nif' => ['NIF / CIF (sale en las facturas)', 'text'],
                    'company_phone' => ['Teléfono de atención', 'text'],
                    'company_email' => ['Correo de contacto', 'email'],
                    'company_address' => ['Dirección', 'text'],
                ],
                'Buscadores (SEO)' => [
                    'seo_meta_title' => ['Título de la web', 'text'],
                    'seo_meta_description' => ['Descripción de la web', 'text'],
                ],
                'Redes sociales' => [
                    'social_twitter' => ['Enlace de Twitter / X', 'url'],
                    'social_linkedin' => ['Enlace de LinkedIn', 'url'],
                ],
            ];
        @endphp

        @foreach ($groups as $title => $fields)
            <div class="space-y-5">
                <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">{{ $title }}</h3>
                @foreach ($fields as $key => [$label, $type])
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2" for="{{ $key }}">{{ $label }}</label>
                        <input type="{{ $type }}" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $configuraciones[$key] ?? '') }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-xs text-gray-700 focus:ring-1 focus:ring-gold-bright" @required($key === 'company_name')>
                    </div>
                @endforeach
            </div>
        @endforeach

        <button type="submit" class="w-full bg-[#A28C3F] hover:bg-[#D5C27A] text-[#1B1E21] font-bold py-3.5 rounded-lg text-xs uppercase tracking-wider transition shadow">
            Guardar configuración
        </button>
    </form>
</x-panel-layout>
