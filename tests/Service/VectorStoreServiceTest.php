<?php

namespace App\Tests\Service;

use App\Entity\Analysis;
use App\Entity\Document;
use App\Enum\DocumentType;
use App\Service\VectorStoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Validates pgvector chunk indexing and cosine similarity retrieval (US04 / #12)
 */
class VectorStoreServiceTest extends KernelTestCase
{
    /**
     * Verifies indexing of reference documents into pgvector and top-k semantic retrieval
     */
    public function testIndexAndSemanticSearchWithPgVector(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $vectorStore = static::getContainer()->get(VectorStoreService::class);

        $analysis = new Analysis();
        $analysis->setTitle('pgvector Semantic Search Test');

        $specDoc = new Document();
        $specDoc->setOriginalFilename('architecture_spec.md');
        $specDoc->setStoragePath('arch-spec.md');
        $specDoc->setMimeType('text/markdown');
        $specDoc->setFileSize(4096);
        $specDoc->setDocumentType(DocumentType::TECHNICAL_SPEC);
        $specDoc->setExtractedText(<<<'MD'
# Availability & SLA
Monthly platform uptime target is 99.5% across regional clusters.

# Encryption & Security
All customer volumes are encrypted at rest with AES-256 and in transit via TLS 1.3.

# Support Escalation
Critical P1 incidents are acknowledged within 4 hours during business hours.
MD);

        $analysis->addDocument($specDoc);
        $em->persist($analysis);
        $em->flush();

        $indexedCount = $vectorStore->indexAnalysisDocuments($analysis);
        $this->assertSame(3, $indexedCount);

        $topChunks = $vectorStore->searchSimilar(
            $analysis->getId(),
            'We guarantee 99.99% monthly uptime availability.',
            2,
        );

        $this->assertCount(2, $topChunks);
        $this->assertStringContainsString('99.5%', $topChunks[0]->text);
        $this->assertGreaterThan(0.3, $topChunks[0]->similarityScore);
    }
}
