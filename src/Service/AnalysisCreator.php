<?php

namespace App\Service;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Enum\DocumentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
     */
    public function __construct(
        private DocumentUploader $documentUploader,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Stores the uploaded files and persists the analysis, removing stored files on any failure
     *
     * @param Analysis $analysis Analysis to persist
     * @param UploadedFile|null $proposalFile Commercial proposal file
     * @param UploadedFile[] $technicalSpecFiles Technical reference files
     * @param string $ownerIdentifier Identifier of the user owning the analysis
     * @param AnalysisStatus $status Initial status of the analysis
     * @throws \InvalidArgumentException When a file is rejected or too many files are provided
     */
    public function create(
        Analysis $analysis,
        ?UploadedFile $proposalFile,
        array $technicalSpecFiles,
        string $ownerIdentifier,
        AnalysisStatus $status = AnalysisStatus::PENDING,
    ): void {
        $technicalSpecFiles = array_values(array_filter($technicalSpecFiles));
        if (count($technicalSpecFiles) > self::MAX_TECHNICAL_SPEC_FILES) {
            throw new \InvalidArgumentException(sprintf('You can upload at most %d technical specification files.', self::MAX_TECHNICAL_SPEC_FILES));
        }

        $analysis->setOwnerIdentifier($ownerIdentifier);
        $analysis->setStatus($status);

        try {
            if ($proposalFile !== null) {
                $analysis->addDocument($this->documentUploader->upload($proposalFile, DocumentType::PROPOSAL));
            }

            foreach ($technicalSpecFiles as $technicalSpecFile) {
                $analysis->addDocument($this->documentUploader->upload($technicalSpecFile, DocumentType::TECHNICAL_SPEC));
            }

            $this->entityManager->persist($analysis);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            // Nettoyer : Supprimer les fichiers déjà déplacés sur le disque
            foreach ($analysis->getDocuments() as $document) {
                $this->documentUploader->remove($document);
            }

            throw $exception;
        }
    }
}
