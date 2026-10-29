@if ($errors->any())
    <div class="mb-8 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-lg text-rose-800 text-xs shadow-sm" role="alert">
        <h4 class="font-bold mb-2">Corrige los siguientes errores:</h4>
        <ul class="list-disc pl-5 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
