<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncEvent;
use App\Support\Sync\PuenteSharePoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Puente con SharePoint por API: Power Automate recoge los avances pendientes y confirma los que ya ha reflejado. */
class EstadosController extends Controller
{
    /** Entrega (una sola vez) los eventos pendientes. Quedan como "exportados" hasta que se confirmen. */
    public function pendientes(): JsonResponse
    {
        $todos = SyncEvent::with('expedient', 'client')->where('estado', 'pendiente')->orderBy('id')->limit(200)->get();
        PuenteSharePoint::marcarExportados($todos, 'api');
        [$deClientes, $eventos] = $todos->partition(fn ($e) => $e->esDeCliente());

        return response()->json([
            'eventos' => $eventos->map->comoFila()->values()->all(),
            // datos de ficha que han aportado los clientes (columnas de la lista de clientes: por confirmar con el despacho)
            'clientes' => $deClientes->map->comoFilaCliente()->values()->all(),
        ]);
    }

    public function confirmar(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['string', 'max:40']]);

        return response()->json(['confirmados' => PuenteSharePoint::confirmar($data['ids'])]);
    }
}
