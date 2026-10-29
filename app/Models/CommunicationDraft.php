<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationDraft extends Model
{
    protected $fillable = ['proposal_id', 'expedient_id', 'tipo', 'idempotency_key', 'recipient', 'subject', 'body', 'attachment_document_id', 'status', 'manifest', 'requested_by', 'remote_id', 'remote_web_link', 'remote_verified_at', 'attempts', 'last_error'];

    protected $casts = ['manifest' => 'array', 'remote_verified_at' => 'datetime'];

    public function expedient(): BelongsTo
    {
        return $this->belongsTo(Expedient::class);
    }

    public function proposal(): BelongsTo { return $this->belongsTo(Proposal::class); }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
