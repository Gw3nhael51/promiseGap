<?php

namespace App\Tests\Service;

use App\DTO\RetrievedChunk;
use App\Enum\FindingStatus;
use App\Service\DiscrepancyClassifier;
use App\Service\EvidenceValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Validates proof-oriented RAG discrepancy qualification across all 4 categories (US05 / #13)
 */
class DiscrepancyClassifierTest extends KernelTestCase
{
    /**
     * Verifies SUPPORTED, CONTRADICTORY, UNSUPPORTED and AMBIGUOUS classifications with evidence enforcement
     */
    public function testQualifiesAllFourDiscrepancyCategories(): void
    {
        self::bootKernel();
        $classifier = static::getContainer()->get(DiscrepancyClassifier::class);

        $chunk995 = new RetrievedChunk(
            text: 'Monthly platform availability target is 99.5% across production zones.',
            sectionTitle: 'SLA & Availability',
            documentTitle: 'specs.md',
            pageNumber: 3,
            chunkIndex: 0,
            similarityScore: 0.91,
        );

        // 1. CONTRADICTORY (99.99% vs 99.5%)
        $conResult = $classifier->qualifyText('We guarantee 99.99% monthly platform availability.', [$chunk995]);
        $this->assertSame(FindingStatus::CONTRADICTORY, $conResult->status);
        $this->assertNotEmpty($conResult->evidences);
        $this->assertStringContainsString('99.5%', $conResult->evidences[0]->quote);

        // 2. SUPPORTED (99.5% vs 99.5%)
        $supResult = $classifier->qualifyText('We guarantee 99.5% monthly platform availability.', [$chunk995]);
        $this->assertSame(FindingStatus::SUPPORTED, $supResult->status);
        $this->assertNotEmpty($supResult->evidences);

        // 3. UNSUPPORTED (no matching chunk)
        $unsResult = $classifier->qualifyText('We provide a dedicated on-site hardware engineer in Tokyo.', []);
        $this->assertSame(FindingStatus::UNSUPPORTED, $unsResult->status);
        $this->assertEmpty($unsResult->evidences);

        // 4. AMBIGUOUS (vague phrasing without metrics)
        $ambResult = $classifier->qualifyText('The platform delivers optimal, world-class, seamless performance.', [$chunk995]);
        $this->assertSame(FindingStatus::AMBIGUOUS, $ambResult->status);
    }

    /**
     * Verifies that EvidenceValidator downgrades SUPPORTED without any chunk to UNSUPPORTED
     */
    public function testEvidenceValidatorDowngradesUnbackedSupportedFinding(): void
    {
        $validator = new EvidenceValidator();
        $result = $validator->validate(FindingStatus::SUPPORTED, 'Looks fine', [], []);

        $this->assertSame(FindingStatus::UNSUPPORTED, $result->status);
        $this->assertEmpty($result->evidences);
    }
}
