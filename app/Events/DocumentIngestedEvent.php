<?php

namespace App\Events;

use App\Models\ExpedientDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentIngestedEvent
{
    use Dispatchable, SerializesModels;

    /**
     * El documento que acaba de ser ingerido.
     *
     * @var ExpedientDocument
     */
    public $document;

    /**
     * Crea una nueva instancia del evento.
     *
     * @param ExpedientDocument $document
     */
    public function __construct(ExpedientDocument $document)
    {
        $this->document = $document;
    }
}
