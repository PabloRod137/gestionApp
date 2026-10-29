<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\DatosCliente;
use Illuminate\Http\Request;

/**
 * "Mis datos": el cliente completa los datos que faltan en su ficha. Solo se rellenan huecos: un dato ya guardado
 * no se puede cambiar desde aquí (se lo pide a su asesor). El cliente sale siempre de la sesión.
 */
class DatosController extends Controller
{
    public function edit(Request $request)
    {
        $cliente = $request->user()->client;
        if (! $cliente) {
            return view('client.no_profile');
        }

        return view('client.datos', [
            'cliente' => $cliente,
            'etiquetas' => DatosCliente::etiquetas(),
            'faltan' => DatosCliente::faltan($cliente),
        ]);
    }

    public function update(Request $request)
    {
        $cliente = $request->user()->client;
        abort_unless($cliente, 403, 'No tienes un perfil de cliente asociado.');

        $faltan = array_keys(DatosCliente::faltan($cliente));
        $enviados = array_intersect_key($request->only($faltan), array_flip($faltan));
        // cada dato escrito tiene que ser válido: el que no lo es se devuelve con su explicación
        $errores = [];
        foreach ($faltan as $campo) {
            $valor = trim((string) ($enviados[$campo] ?? ''));
            if ($valor !== '' && DatosCliente::normalizar($campo, $valor) === null) {
                $errores[$campo] = DatosCliente::mensajeError($campo);
            }
        }
        if ($errores) {
            return back()->withErrors($errores)->withInput();
        }

        $guardados = DatosCliente::aportar($cliente, $request->user(), $enviados, $request->ip());
        // lo que se escribió y no se pudo guardar (por ejemplo un NIF que ya consta en otra ficha) no se explica: se remite al despacho
        $sinGuardar = array_diff(array_keys(array_filter($enviados, fn ($v) => trim((string) $v) !== '')), $guardados);
        if ($sinGuardar) {
            return back()->withInput()->with('error', 'No hemos podido guardar: '.implode(', ', array_map(fn ($c) => DatosCliente::etiquetas()[$c], $sinGuardar)).'. Escribe a tu asesor y lo revisa contigo.');
        }
        if (! $guardados) {
            return back()->with('error', 'No has escrito ningún dato.');
        }

        return redirect()->route('cliente.datos.edit')->with('success', 'Gracias, hemos guardado tus datos.');
    }
}
