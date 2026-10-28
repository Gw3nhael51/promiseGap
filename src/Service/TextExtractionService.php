<?php

namespace App\Service;

use App\Entity\Document;
use App\Enum\ExtractionStatus;
use App\Exception\TextExtractionException;

/**
 * Extracts the text of a stored document and records the outcome on the entity
 */
class TextExtractionService
{
    /**
     * Initializes the text extraction service
     *
     * @param DocumentUploader $documentUploader Service giving access to stored files
     * @param PdfExtractor $pdfExtractor Extractor for PDF files
     * @param MarkdownExtractor $markdownExtractor Extractor for Markdown and plain text files
     */
    public function __construct(
        private DocumentUploader $documentUploader,
        private PdfExtractor $pdfExtractor,
        private MarkdownExtractor $markdownExtractor,
    ) {
    }

    /**
     * Extracts the text of a document and updates its extraction fields (caller must flush)
     *
     * @param Document $document Document to process
     */
    public function process(Document $document): void
    {
        try {
            $result = $this->extract($document);

            $document->setExtractedText($result->text);
            $document->setCharacterCount($result->characterCount());
            $document->setEstimatedTokens($result->estimatedTokens());
            $document->setExtractionError(null);
            $document->setExtractionStatus(ExtractionStatus::DONE);
        } catch (TextExtractionException $exception) {
            $document->setExtractedText(null);
            $document->setCharacterCount(null);
            $document->setEstimatedTokens(null);
            $document->setExtractionError(mb_substr($exception->getMessage(), 0, 500));
            $document->setExtractionStatus(ExtractionStatus::FAILED);
        }
    }

    /**
     * Selects the extractor matching the document type and runs it
     *
     * @param Document $document Document to extract
     * @return ExtractionResult Extracted text
     * @throws TextExtractionException When the document cannot be extracted
     */
    private function extract(Document $document): ExtractionResult
    {
        $path = $this->documentUploader->getTargetDirectory() . '/' . basename((string) $document->getStoragePath());

        return match ($document->getMimeType()) {
            'application/pdf' => $this->pdfExtractor->extract($path),
            'text/plain', 'text/markdown', 'text/x-markdown' => $this->markdownExtractor->extract($path),
            default => throw new TextExtractionException('This document format is not supported.'),
        };
    }
}
