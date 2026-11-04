<?php

namespace App\Service;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Enum\DocumentType;
use App\Message\ExtractDocumentText;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates an analysis workspace with its documents in an all-or-nothing way
 */
class AnalysisCreator
{
    public const int MAX_TECHNICAL_SPEC_FILES = 10;

    /**
     * Initializes the analysis creation service
     *
     * @param DocumentUploader $documentUploader Service storing uploaded files
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param MessageBusInterface $messageBus Bus dispatching the text extraction requests
     */
    public function __construct(
        private DocumentUploader $documentUploader,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Stores the uploaded files and persists the analysis, removing stored files on any failure
     *
     * @param Analysis $analysis Analysis to persist
     * @param UploadedFile|UploadedFile[]|null $proposalFiles Commercial proposal file or list of files
     * @param UploadedFile[] $technicalSpecFiles Technical reference files
     * @param string $ownerIdentifier Identifier of the user owning the analysis
     * @param AnalysisStatus $status Initial status of the analysis
     * @return void
     * @throws \InvalidArgumentException When a file is rejected or too many files are provided
     */
    public function create(
        Analysis $analysis,
        UploadedFile|array|null $proposalFiles,
        array $technicalSpecFiles,
        string $ownerIdentifier,
        AnalysisStatus $status = AnalysisStatus::PENDING,
    ): void {
        if (is_array($proposalFiles)) {
            $proposalList = array_values(array_filter($proposalFiles));
        } elseif ($proposalFiles !== null) {
            $proposalList = [$proposalFiles];
        } else {
            $proposalList = [];
        }

        if (count($proposalList) > self::MAX_TECHNICAL_SPEC_FILES) {
            throw new \InvalidArgumentException(sprintf('You can upload at most %d commercial proposal files.', self::MAX_TECHNICAL_SPEC_FILES));
        }

        $technicalSpecFiles = array_values(array_filter($technicalSpecFiles));
        if (count($technicalSpecFiles) > self::MAX_TECHNICAL_SPEC_FILES) {
            throw new \InvalidArgumentException(sprintf('You can upload at most %d technical specification files.', self::MAX_TECHNICAL_SPEC_FILES));
        }

        $analysis->setOwnerIdentifier($ownerIdentifier);
        $analysis->setStatus($status);

        try {
            foreach ($proposalList as $proposalFile) {
                $analysis->addDocument($this->documentUploader->upload($proposalFile, DocumentType::PROPOSAL));
            }

            foreach ($technicalSpecFiles as $technicalSpecFile) {
                $analysis->addDocument($this->documentUploader->upload($technicalSpecFile, DocumentType::TECHNICAL_SPEC));
            }

            $this->entityManager->persist($analysis);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            foreach ($analysis->getDocuments() as $document) {
                $this->documentUploader->remove($document);
            }

            throw $exception;
        }

        foreach ($analysis->getDocuments() as $document) {
            $this->messageBus->dispatch(new ExtractDocumentText($document->getId()));
        }
    }
}
