<?php

declare(strict_types=1);

namespace Twila\Credicarro\Proposals;

final class ProposalDocumentUpload
{
    /** @var string um dos ProposalParticipant::* (`participante`) */
    public $participant;

    /** @var int um dos DocumentType::* (`tipoDocumento`) */
    public $documentType;

    /** @var DocumentFile */
    public $file;

    public function __construct(string $participant, int $documentType, DocumentFile $file)
    {
        $this->participant = $participant;
        $this->documentType = $documentType;
        $this->file = $file;
    }
}
