{{-- Botón de confirmar un servicio sugerido. Variables: $serviceId, $origen, $svc (servicio actual o null). --}}
<form method="POST" action="{{ route('panel.expedientes.asignar-servicio', $expediente->id) }}" class="shrink-0">
    @csrf
    <input type="hidden" name="service_id" value="{{ $serviceId }}">
    <input type="hidden" name="origen" value="{{ $origen }}">
    @if($svc)<input type="hidden" name="cambiar" value="1">@endif
    <button class="bg-white border border-gray-300 text-gray-700 font-bold px-3 py-1.5 rounded text-[10px] uppercase tracking-wider">{{ $svc ? 'Cambiar a este' : 'Asignar este' }}</button>
</form>
