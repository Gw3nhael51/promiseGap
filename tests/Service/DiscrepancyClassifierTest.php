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

    /**
     * Verifies adaptive reasoning effort ('auto') escalation and explicit user overrides ('low', 'medium', 'high')
     */
    public function testAdaptiveAndExplicitReasoningEffortResolution(): void
    {
        self::bootKernel();
        $classifier = static::getContainer()->get(DiscrepancyClassifier::class);

        $grayZoneChunk = new RetrievedChunk(
            text: 'Monthly platform availability target is 99.5% excluding scheduled maintenance.',
            sectionTitle: 'SLA & Availability',
            documentTitle: 'specs.md',
            pageNumber: 1,
            chunkIndex: 0,
            similarityScore: 0.58,
        );

        $highConfidenceChunk = new RetrievedChunk(
            text: 'All data at rest is encrypted with AES-256.',
            sectionTitle: 'Security',
            documentTitle: 'specs.md',
            pageNumber: 1,
            chunkIndex: 0,
            similarityScore: 0.92,
        );

        // 1. Auto mode with no chunks -> low
        $this->assertSame('low', $classifier->resolveReasoningEffort('We guarantee 99.99% uptime.', [], 'auto'));

        // 2. Auto mode with high-stakes SLA/penalty clause and borderline similarity (0.58) -> high
        $this->assertSame(
            'high',
            $classifier->resolveReasoningEffort(
                'The platform guarantees 99.99% monthly availability with financial penalties.',
                [$grayZoneChunk],
                'auto',
            ),
        );

        // 3. Auto mode with high-stakes clause and very high similarity (0.92) -> medium
        $this->assertSame(
            'medium',
            $classifier->resolveReasoningEffort(
                'The hosting infrastructure is ISO 27001 and SOC 2 certified.',
                [$highConfidenceChunk],
                'auto',
            ),
        );

        // 4. Auto mode with quantitative timeline metric -> medium
        $this->assertSame(
            'medium',
            $classifier->resolveReasoningEffort(
                'Migration will be delivered within 4 weeks.',
                [$highConfidenceChunk],
                'auto',
            ),
        );

        // 5. Auto mode with qualitative non-metric claim -> low
        $this->assertSame(
            'low',
            $classifier->resolveReasoningEffort(
                'The user interface is modern and intuitive.',
                [$highConfidenceChunk],
                'auto',
            ),
        );

        // 6. Explicit user overrides always take precedence
        $this->assertSame('low', $classifier->resolveReasoningEffort('99.99% SLA with penalties', [$grayZoneChunk], 'low'));
        $this->assertSame('medium', $classifier->resolveReasoningEffort('Simple qualitative claim', [$highConfidenceChunk], 'medium'));
        $this->assertSame('high', $classifier->resolveReasoningEffort('Simple qualitative claim', [], 'high'));
    }
}
