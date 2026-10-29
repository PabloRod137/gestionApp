<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito de trabajo diario del despacho (traido de su panel V3): propuestas con revisiones,
 * actividad por expediente y solicitudes de comunicacion. Nada se envia: solo se deja preparado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expedient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('purpose');
            // proposed -> approved -> generated -> document_approved -> communication_requested | rejected
            $table->string('status', 32)->default('proposed')->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('source_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('generated_document_id')->nullable()->constrained('expedient_documents')->nullOnDelete();
            $table->foreignId('pdf_document_id')->nullable()->constrained('expedient_documents')->nullOnDelete();
            $table->string('document_sha256', 64)->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->string('document_key', 64)->nullable();
            $table->string('approved_document_key', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('proposal_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('data');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['proposal_id', 'version']);
        });

        Schema::create('case_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expedient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('communication_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('recipient', 254);
            $table->string('subject', 250);
            $table->text('body');
            $table->foreignId('attachment_document_id')->nullable()->constrained('expedient_documents')->nullOnDelete();
            // el borrador real (Outlook) lo creara un adaptador aparte; mientras tanto solo queda preparado
            $table->string('status', 32)->default('pendiente_adaptador');
            $table->json('manifest');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_drafts');
        Schema::dropIfExists('case_activities');
        Schema::dropIfExists('proposal_revisions');
        Schema::dropIfExists('proposals');
    }
};
