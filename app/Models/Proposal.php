<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proposal extends Model
{
    public const ESTADOS = [
        'proposed' => 'Propuesta',
        'approved' => 'Aprobada',
        'generated' => 'Documento en revisión',
        'document_approved' => 'Documento aprobado',
        'communication_requested' => 'Comunicación preparada',
        'rejected' => 'Rechazada',
    ];

    protected $fillable = [
        'expedient_id', 'document_template_id', 'title', 'purpose', 'status', 'version', 'source_hash', 'created_by', 'approved_by',
        'document_approved_by', 'rejection_reason', 'generated_document_id', 'pdf_document_id', 'document_sha256', 'pdf_sha256',
        'document_key', 'approved_document_key',
    ];

    public function expedient(): BelongsTo { return $this->belongsTo(Expedient::class); }

    public function template(): BelongsTo { return $this->belongsTo(DocumentTemplate::class, 'document_template_id'); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }

    public function documentApprover(): BelongsTo { return $this->belongsTo(User::class, 'document_approved_by'); }

    public function generatedDocument(): BelongsTo { return $this->belongsTo(ExpedientDocument::class, 'generated_document_id'); }

    public function pdfDocument(): BelongsTo { return $this->belongsTo(ExpedientDocument::class, 'pdf_document_id'); }

    public function revisions(): HasMany { return $this->hasMany(ProposalRevision::class)->orderBy('version'); }

    public function activities(): HasMany { return $this->hasMany(CaseActivity::class)->latest('id'); }

    public function communicationDraft(): HasOne { return $this->hasOne(CommunicationDraft::class); }

    public function etiqueta(): string
    {
        return self::ESTADOS[$this->status] ?? $this->status;
    }
}
