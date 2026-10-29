<?php

namespace App\Listeners;

use App\Events\DocumentIngestedEvent;
use App\Mail\DocumentIngestedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendDocumentIngestedNotification
{
    /**
     * Maneja el evento y envía notificaciones por correo al personal correspondiente.
     */
    public function handle(DocumentIngestedEvent $event): void
    {
        $document = $event->document;
        $recipients = [];

        // 1. Si está asociado a un expediente con asesor asignado, le notificamos a él
        if ($document->expedient && $document->expedient->worker) {
            $recipients[] = $document->expedient->worker->email;
        }

        // 2. Si es un documento general del cliente o el expediente no tiene asesor asignado, notificamos a todos los asesores y administradores
        if (empty($recipients)) {
            try {
                $staff = User::role(['worker', 'admin'])->get();
                foreach ($staff as $person) {
                    $recipients[] = $person->email;
                }
            } catch (\Exception $e) {
                // Fallback si Spatie roles tiene problemas temporales
                $staff = User::whereIn('id', [1, 2, 3])->get(); // IDs típicos
                foreach ($staff as $person) {
                    $recipients[] = $person->email;
                }
            }
        }

        // Eliminar duplicados o nulos
        $recipients = array_filter(array_unique($recipients));

        if (!empty($recipients)) {
            try {
                Mail::to($recipients)->send(new DocumentIngestedNotification($document));
                Log::info("DocumentIngestionListener: Notificación enviada por email a: " . implode(', ', $recipients));
            } catch (\Exception $e) {
                Log::error("DocumentIngestionListener: Error al enviar correo de notificación: " . $e->getMessage());
            }
        }
    }
}
