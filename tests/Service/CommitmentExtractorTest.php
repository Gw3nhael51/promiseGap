<?php

namespace App\Tests\Service;

use App\Entity\Analysis;
use App\Entity\Document;
use App\Enum\DocumentType;
use App\Service\CommitmentExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Validates commercial commitment extraction and persistence (US03 / #10)
 */
class CommitmentExtractorTest extends KernelTestCase
{
    /**
     * Verifies extraction of SLA, support and security commitments with page numbers
     */
    public function testExtractFromTextAndPersistForAnalysis(): void
    {
        self::bootKernel();
        $extractor = static::getContainer()->get(CommitmentExtractor::class);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $proposalText = <<<'TEXT'
[Page 1]
# Executive Proposal
We guarantee 99.99% monthly platform availability across all production regions.

[Page 2]
## Support & Security
Our team will provide 24/7 P1 critical incident response within 15 minutes.
All customer data is protected under SOC 2 Type II and ISO 27001 certifications.
TEXT;

        $result = $extractor->extractFromText($proposalText);
        $this->assertGreaterThanOrEqual(3, $result->count());
        $this->assertSame(1, $result->commitments[0]->sourcePage);
        $this->assertSame('SLA', $result->commitments[0]->category);

        $analysis = new Analysis();
        $analysis->setTitle('Commitment Extraction Test');

        $doc = new Document();
        $doc->setOriginalFilename('proposal.pdf');
        $doc->setStoragePath('proposal-test.pdf');
        $doc->setMimeType('application/pdf');
        $doc->setFileSize(2048);
        $doc->setDocumentType(DocumentType::PROPOSAL);
        $doc->setExtractedText($proposalText);
        $analysis->addDocument($doc);

        $em->persist($analysis);
        $em->flush();

        $persisted = $extractor->extractForAnalysis($analysis);
        $this->assertGreaterThanOrEqual(3, count($persisted));
        $this->assertNotNull($persisted[0]->getId());
    }
}
