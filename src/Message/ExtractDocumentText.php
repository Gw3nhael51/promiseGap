<?php

namespace App\Message;

/**
 * Asks the workers to extract the text of an uploaded document
 */
final readonly class ExtractDocumentText
{
    /**
     * @param int $documentId Identifier of the document to process
     */
    public function __construct(
        public int $documentId,
    ) {
    }
}
