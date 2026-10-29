<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CaseActivity;
use App\Models\CommunicationDraft;
use App\Models\Expedient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Comunicaciones sin documento. Solo quedan como solicitud de borrador: el programa nunca envía. */
class ComunicacionController extends Controller
{
    public const TIPOS = [
        'COMUNICACION_LIBRE' => 'Comunicación libre', 'SOLICITUD_DOCUMENTACION' => 'Solicitud de documentación', 'RECORDATORIO' => 'Recordatorio',
        'INFORMACION' => 'Información', 'RECLAMACION' => 'Reclamación', 'ENVIO_DOCUMENTO' => 'Envío de documento',
    ];

    public function store(Request $request, Expedient $expedient)
    {
        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:254', 'regex:/^[^\s@,;<>]+@[^\s@,;<>]+\.[^\s@,;<>]+$/'],
            'subject' => ['required', 'string', 'max:250', 'regex:/^[^\r\n]+$/'],
            'body' => ['required', 'string', 'max:20000'],
            'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'confirmado' => ['accepted'],
        ], [
            'recipient.regex' => 'Introduce un único correo válido y revisado.', 'recipient.required' => 'Indica el destinatario.', 'subject.required' => 'Indica el asunto.',
            'subject.regex' => 'El asunto debe ocupar una línea.', 'body.required' => 'Escribe el mensaje.', 'confirmado.accepted' => 'Confirma que has revisado destinatario, asunto y mensaje.',
        ]);

        $com = ['recipient' => strtolower(trim($data['recipient'])), 'subject' => trim($data['subject']), 'body' => trim($data['body'])];
        $manifest = [
            'schema' => 'panel-despacho/1', 'command' => 'CREATE_OR_UPDATE_OUTLOOK_DRAFT', 'case_id' => $expedient->codigo, 'tipo' => $data['tipo'],
            'communication' => $com, 'attachments' => [], 'send_permitted' => false,
        ];
        $clave = hash('sha256', json_encode($manifest, JSON_UNESCAPED_UNICODE));

        // la misma comunicación dos veces no se duplica
        $d = CommunicationDraft::firstOrCreate(['idempotency_key' => $clave], [
            'expedient_id' => $expedient->id, 'recipient' => $com['recipient'], 'subject' => $com['subject'], 'body' => $com['body'],
            'status' => 'pendiente_adaptador', 'manifest' => $manifest, 'tipo' => $data['tipo'], 'requested_by' => $request->user()->id,
        ]);
        if ($d->wasRecentlyCreated) {
            CaseActivity::registrar($expedient, 'COMUNICACION_PREPARADA', self::TIPOS[$data['tipo']].'. No se ha enviado nada.', $request->user());
        }

        return redirect()->route('panel.expedientes.show', $expedient->id)
            ->with('success', 'Comunicación preparada. No se ha enviado nada: se crea como borrador en el correo del despacho para que la revises y la envíes tú.');
    }
}
