<?php

namespace App\MessageHandler;

use App\Enum\ExtractionStatus;
use App\Message\ExtractDocumentText;
use App\Repository\DocumentRepository;
use App\Service\TextExtractionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles asynchronous text extraction requests
 */
#[AsMessageHandler]
final readonly class ExtractDocumentTextHandler
{
    /**
     * @param DocumentRepository $documentRepository Repository used to load the document
     * @param TextExtractionService $textExtractionService Service performing the extraction
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     */
    public function __construct(
        private DocumentRepository $documentRepository,
        private TextExtractionService $textExtractionService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Extracts the text of the requested document unless it was deleted or already processed
     *
     * @param ExtractDocumentText $message Extraction request
     */
    public function __invoke(ExtractDocumentText $message): void
    {
        $document = $this->documentRepository->find($message->documentId);
        if ($document === null || $document->getExtractionStatus() !== ExtractionStatus::PENDING) {
            return;
        }

        $this->textExtractionService->process($document);
        $this->entityManager->flush();
    }
}
