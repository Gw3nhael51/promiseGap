<?php

namespace App\Tests\Repository;

use App\Entity\Analysis;
use App\Entity\Document;
use App\Enum\AnalysisStatus;
use App\Enum\DocumentType;
use App\Repository\AnalysisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests persistence and database operations for Analysis and Document entities
 */
class AnalysisRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?AnalysisRepository $analysisRepository = null;

    /**
     * Sets up test kernel and initializes Doctrine dependencies
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
        $this->analysisRepository = static::getContainer()->get(AnalysisRepository::class);
    }

    /**
     * Verifies cascading persistence of an analysis and its attached documents
     */
    public function testPersistAnalysisWithCascadeDocuments(): void
    {
        $analysis = new Analysis();
        $analysis->setTitle('Contract Audit Test');
        $analysis->setStatus(AnalysisStatus::PROCESSING);

        $document = new Document();
        $document->setOriginalFilename('specs.pdf')
            ->setStoragePath('uploads/specs_123.pdf')
            ->setMimeType('application/pdf')
            ->setFileSize(2048)
            ->setDocumentType(DocumentType::TECHNICAL_SPEC)
            ->setExtractedText('Technical specifications content');

        $analysis->addDocument($document);

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        $savedId = $analysis->getId();
        $this->assertNotNull($savedId);

        $this->entityManager->clear();

        $retrieved = $this->analysisRepository->find($savedId);
        $this->assertNotNull($retrieved);
        $this->assertSame('Contract Audit Test', $retrieved->getTitle());
        $this->assertSame(AnalysisStatus::PROCESSING, $retrieved->getStatus());
        $this->assertCount(1, $retrieved->getDocuments());

        $firstDoc = $retrieved->getDocuments()->first();
        $this->assertSame('specs.pdf', $firstDoc->getOriginalFilename());
        $this->assertSame(DocumentType::TECHNICAL_SPEC, $firstDoc->getDocumentType());

        $this->entityManager->remove($retrieved);
        $this->entityManager->flush();

        $this->assertNull($this->analysisRepository->find($savedId));
    }

    /**
     * Cleans up Doctrine entity manager after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
    }
}
