<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    /**
     * Listado y búsqueda de clientes.
     */
    public function index(Request $request)
    {
        $buscar = $request->input('search');

        $clientes = Client::with('user')
            ->when($buscar, function ($query, $buscar) {
                $query->where('razon_social', 'like', "%{$buscar}%")
                      ->orWhere('codigo', 'like', "%{$buscar}%")
                      ->orWhere('email', 'like', "%{$buscar}%")
                      ->orWhere('nif', 'like', "%{$buscar}%")
                      ->orWhereHas('user', function ($q) use ($buscar) {
                          $q->where('email', 'like', "%{$buscar}%");
                      });
            })
            ->orderBy('razon_social', 'asc')
            ->paginate(10);

        return view('panel.clientes.index', compact('clientes', 'buscar'));
    }

    /**
     * Formulario de creación de cliente.
     */
    public function create()
    {
        return view('panel.clientes.create');
    }

    /**
     * Guarda un nuevo cliente y opcionalmente su cuenta de acceso al portal.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nif' => 'required|string|unique:clients,nif|max:20',
            'razon_social' => 'required|string|max:255',
            'tipo' => 'required|in:particular,autónomo,empresa',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
            
            // Campos de la cuenta de acceso opcional
            'crear_cuenta' => 'nullable|boolean',
            'name' => 'required_if:crear_cuenta,1|nullable|string|max:255',
            'email' => 'required_if:crear_cuenta,1|nullable|email|unique:users,email|max:255',
            'password' => 'required_if:crear_cuenta,1|nullable|string|min:8',
        ], [
            'nif.unique' => 'El NIF/CIF introducido ya está registrado en el sistema.',
            'email.unique' => 'El correo electrónico ya está registrado para otro usuario.',
            'name.required_if' => 'El nombre del usuario es obligatorio si deseas crear una cuenta.',
            'email.required_if' => 'El email es obligatorio si deseas crear una cuenta.',
            'password.required_if' => 'La contraseña es obligatoria si deseas crear una cuenta.',
        ]);

        $userId = null;

        // Crear la cuenta de usuario si se marcó el checkbox
        if ($request->boolean('crear_cuenta')) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Asignar el rol de cliente
            $user->assignRole('client');
            $userId = $user->id;
        }

        // Crear la ficha de cliente
        Client::create([
            'user_id' => $userId,
            'nif' => $validated['nif'],
            'razon_social' => $validated['razon_social'],
            'tipo' => $validated['tipo'],
            'telefono' => $validated['telefono'],
            'direccion' => $validated['direccion'],
            'notas' => $validated['notas'],
        ]);

        return redirect()->route('panel.clientes.index')
            ->with('success', 'Cliente creado con éxito.');
    }

    /**
     * Muestra el detalle del cliente.
     */
    public function show($id)
    {
        $cliente = Client::with(['user', 'expedients.service', 'appointments.service', 'invoices'])->findOrFail($id);
        return view('panel.clientes.show', compact('cliente'));
    }

    /**
     * Formulario de edición del cliente.
     */
    public function edit($id)
    {
        $cliente = Client::with('user')->findOrFail($id);
        return view('panel.clientes.edit', compact('cliente'));
    }

    /**
     * Actualiza la información del cliente y de su cuenta vinculada.
     */
    public function update(Request $request, $id)
    {
        $cliente = Client::findOrFail($id);

        $validated = $request->validate([
            'nif' => ['required', 'string', 'max:20', Rule::unique('clients', 'nif')->ignore($cliente->id)],
            'razon_social' => 'required|string|max:255',
            'tipo' => 'required|in:particular,autónomo,empresa',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
            
            // Editar contraseña u datos de usuario vinculados
            'name' => 'nullable|string|max:255',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($cliente->user_id)],
            'password' => 'nullable|string|min:8',
        ]);

        // Actualizar ficha del cliente
        $cliente->update([
            'nif' => $validated['nif'],
            'razon_social' => $validated['razon_social'],
            'tipo' => $validated['tipo'],
            'telefono' => $validated['telefono'],
            'direccion' => $validated['direccion'],
            'notas' => $validated['notas'],
        ]);

        // Si tiene usuario vinculado, actualizarlo
        if ($cliente->user_id) {
            $user = $cliente->user;
            $userUpdates = [];

            if ($request->filled('name')) {
                $userUpdates['name'] = $validated['name'];
            }
            if ($request->filled('email')) {
                $userUpdates['email'] = $validated['email'];
            }
            if ($request->filled('password')) {
                $userUpdates['password'] = Hash::make($validated['password']);
            }

            if (!empty($userUpdates)) {
                $user->update($userUpdates);
            }
        }

        return redirect()->route('panel.clientes.index')
            ->with('success', 'Cliente actualizado con éxito.');
    }

    /**
     * Realiza el borrado lógico del cliente.
     */
    public function destroy($id)
    {
        $cliente = Client::findOrFail($id);
        $cliente->delete();

        return redirect()->route('panel.clientes.index')
            ->with('success', 'Cliente dado de baja correctamente.');
    }

    /**
     * Permite a un asesor/administrador iniciar sesión como el cliente seleccionado (Impersonación).
     */
    public function impersonate($id)
    {
        $cliente = Client::findOrFail($id);

        if (!$cliente->user_id) {
            return back()->with('error', 'Este cliente no tiene una cuenta de usuario activa para acceder al portal.');
        }

        $userToImpersonate = $cliente->user;

        // Validar que el usuario que intenta suplantar tiene permisos (workers o admins)
        if (!auth()->user()->hasRole('admin') && !auth()->user()->hasRole('worker')) {
            abort(403, 'Acción no autorizada.');
        }

        // Iniciar impersonación
        auth()->user()->impersonate($userToImpersonate);

        // Redirigir al dashboard del cliente suplantado
        return redirect()->route('cliente.dashboard')
            ->with('success', "Has iniciado sesión en el portal como: {$cliente->razon_social}");
    }

    /**
     * Exporta los clientes a un archivo Excel.
     */
    public function exportExcel()
    {
        return Excel::download(new \App\Exports\ClientsExport, 'clientes-nortia.xlsx');
    }

    /**
     * Importa los clientes desde un archivo Excel cargado.
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'archivo_excel.required' => 'El archivo Excel es obligatorio para realizar la importación.',
            'archivo_excel.mimes' => 'El archivo debe estar en formato Excel (.xlsx, .xls o .csv).',
        ]);

        Excel::import(new \App\Imports\ClientsImport, $request->file('archivo_excel'));

        return redirect()->route('panel.clientes.index')
            ->with('success', 'Clientes importados con éxito desde el archivo Excel.');
    }
}
