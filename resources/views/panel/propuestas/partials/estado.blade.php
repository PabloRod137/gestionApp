@php
    $colores = [
        'proposed' => 'bg-amber-50 text-amber-800 border-amber-200',
        'approved' => 'bg-sky-50 text-sky-800 border-sky-200',
        'generated' => 'bg-violet-50 text-violet-800 border-violet-200',
        'document_approved' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'communication_requested' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
        'rejected' => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
@endphp
<span class="inline-block border font-bold px-2 py-0.5 rounded-full text-[9px] uppercase tracking-wider {{ $colores[$p->status] ?? 'bg-gray-100 text-gray-600 border-gray-200' }}">{{ $p->etiqueta() }}</span>
