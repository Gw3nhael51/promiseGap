<?php

namespace App\Tests\Entity;

use App\Entity\Analysis;
use App\Entity\Document;
use App\Enum\AnalysisStatus;
use App\Enum\DocumentType;
use PHPUnit\Framework\TestCase;

/**
 * Tests business logic and relations for Analysis and Document entities
 */
class AnalysisEntityTest extends TestCase
{
    /**
     * Verifies initialization and default values of an analysis
     */
    public function testAnalysisInitialization(): void
    {
        $analysis = new Analysis();
        $analysis->setTitle('Q4 RFP Audit');

        $this->assertSame('Q4 RFP Audit', $analysis->getTitle());
        $this->assertSame(AnalysisStatus::PENDING, $analysis->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $analysis->getCreatedAt());
        $this->assertCount(0, $analysis->getDocuments());
    }

    /**
     * Verifies bidirectional handling of attached documents
     */
    public function testAddAndRemoveDocument(): void
    {
        $analysis = new Analysis();
        $analysis->setTitle('Vendor RFP');

        $document = new Document();
        $document->setOriginalFilename('proposal.pdf')
            ->setStoragePath('uploads/proposal.pdf')
            ->setMimeType('application/pdf')
            ->setFileSize(1024)
            ->setDocumentType(DocumentType::PROPOSAL);

        $analysis->addDocument($document);

        $this->assertCount(1, $analysis->getDocuments());
        $this->assertSame($analysis, $document->getAnalysis());

        $analysis->removeDocument($document);

        $this->assertCount(0, $analysis->getDocuments());
        $this->assertNull($document->getAnalysis());
    }

    /**
     * Verifies status label and badge class helpers
     */
    public function testAnalysisStatusBadges(): void
    {
        $this->assertSame('badge-neutral', AnalysisStatus::PENDING->badgeClass());
        $this->assertSame('badge-warning', AnalysisStatus::PROCESSING->badgeClass());
        $this->assertSame('badge-success', AnalysisStatus::COMPLETED->badgeClass());
        $this->assertSame('badge-error', AnalysisStatus::FAILED->badgeClass());
    }
}
