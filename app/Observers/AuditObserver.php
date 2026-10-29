<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditObserver
{
    /**
     * Se ejecuta tras crear un registro.
     */
    public function created(Model $model): void
    {
        // Se registran todos los datos iniciales
        $this->log($model, 'creado', null, $model->getAttributes());
    }

    /**
     * Se ejecuta tras modificar un registro.
     */
    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        
        // Si no hay cambios reales, se sale
        if (empty($dirty)) {
            return;
        }

        $anteriores = [];
        $nuevos = [];

        foreach ($dirty as $key => $value) {
            // Se omiten marcas de tiempo del registro de auditoría
            if (in_array($key, ['updated_at'])) {
                continue;
            }
            $anteriores[$key] = $model->getOriginal($key);
            $nuevos[$key] = $value;
        }

        // Si solo se modificó el timestamp, no registramos
        if (empty($nuevos)) {
            return;
        }

        $this->log($model, 'modificado', $anteriores, $nuevos);
    }

    /**
     * Se ejecuta tras eliminar un registro.
     */
    public function deleted(Model $model): void
    {
        // Se registra el estado previo del modelo borrado
        $this->log($model, 'eliminado', $model->getAttributes(), null);
    }

    /**
     * Crea un registro en la tabla de logs de auditoría.
     */
    protected function log(Model $model, string $accion, ?array $anteriores, ?array $nuevos): void
    {
        // Evitamos bucles si estuviéramos auditando la misma tabla de auditoría (que no es el caso)
        AuditLog::create([
            'user_id' => Auth::id(), // Usuario autenticado
            'accion' => $accion, // 'creado', 'modificado', 'eliminado'
            'modelo' => get_class($model), // Nombre completo del modelo (clase)
            'modelo_id' => $model->getKey(), // Clave primaria
            'datos_anteriores' => $anteriores, // Datos antes del cambio
            'datos_nuevos' => $nuevos, // Datos después del cambio
            'ip' => Request::ip(), // IP de origen
        ]);
    }
}
