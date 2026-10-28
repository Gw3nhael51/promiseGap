<?php

namespace App\Tests\Entity;

use App\Entity\Analysis;
use App\Entity\Commitment;
use App\Entity\Document;
use App\Entity\Evidence;
use App\Entity\Finding;
use App\Enum\DocumentType;
use App\Enum\FindingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Validates persistence and cascade relations of Commitment, Finding and Evidence entities
 */
class CommitmentFindingEvidenceTest extends KernelTestCase
{
    /**
     * Verifies full persistence and cascade deletion across Analysis, Commitment, Finding and Evidence
     */
    public function testPersistAndCascadeDeleteCommitmentFindingEvidence(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $analysis = new Analysis();
        $analysis->setTitle('Entity Graph Test');

        $doc = new Document();
        $doc->setOriginalFilename('spec.md');
        $doc->setStoragePath('spec-123.md');
        $doc->setMimeType('text/markdown');
        $doc->setFileSize(512);
        $doc->setDocumentType(DocumentType::TECHNICAL_SPEC);
        $analysis->addDocument($doc);

        $commitment = new Commitment();
        $commitment->setVerbatimText('We guarantee 99.99% monthly uptime.');
        $commitment->setCategory('SLA');
        $commitment->setConfidenceScore(0.96);
        $commitment->setSourcePage(2);

        $finding = new Finding();
        $finding->setStatus(FindingStatus::CONTRADICTORY);
        $finding->setRationale('Proposal promises 99.99% whereas spec states 99.5%.');
        $finding->setIsVerifiedByHuman(true);

        $evidence = new Evidence();
        $evidence->setDocument($doc);
        $evidence->setQuote('Monthly availability target is 99.5%.');
        $evidence->setSectionTitle('3.1 Availability');
        $evidence->setPageNumber(4);
        $evidence->setSimilarityScore(0.91);

        $finding->addEvidence($evidence);
        $commitment->setFinding($finding);
        $analysis->addCommitment($commitment);

        $em->persist($analysis);
        $em->flush();

        $commitmentId = $commitment->getId();
        $findingId = $finding->getId();
        $evidenceId = $evidence->getId();

        $this->assertNotNull($commitmentId);
        $this->assertNotNull($findingId);
        $this->assertNotNull($evidenceId);
        $this->assertSame('con', $finding->getStatus()->shortCode());

        $em->remove($analysis);
        $em->flush();
        $em->clear();

        $this->assertNull($em->find(Commitment::class, $commitmentId));
        $this->assertNull($em->find(Finding::class, $findingId));
        $this->assertNull($em->find(Evidence::class, $evidenceId));
    }
}
