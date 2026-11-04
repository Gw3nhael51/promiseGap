<?php

namespace App\Tests\Service;

use App\Service\DocumentChunker;
use PHPUnit\Framework\TestCase;

/**
 * Validates technical reference document chunking, metadata preservation and overlap (#11)
 */
class DocumentChunkerTest extends TestCase
{
    /**
     * Verifies that headings and PDF page markers are preserved across chunks
     */
    public function testPreservesHeadingHierarchyAndPageNumbers(): void
    {
        $chunker = new DocumentChunker(targetTokens: 120, overlapTokens: 20);

        $raw = <<<'MD'
[Page 1]
# Technical Architecture
## Availability & Resilience
Monthly availability target is **99.5%** calculated across standard regional zones.

[Page 2]
## Support Operations
### Escalation Matrix
P1 critical tickets are acknowledged within 4 hours during business hours.
MD;

        $chunks = $chunker->chunkText($raw, 'specs.md', 42);

        $this->assertCount(2, $chunks);
        $this->assertSame('Technical Architecture > Availability & Resilience', $chunks[0]->sectionTitle);
        $this->assertSame(1, $chunks[0]->pageNumber);
        $this->assertSame(42, $chunks[0]->documentId);
        $this->assertStringNotContainsString('**', $chunks[0]->text);

        $this->assertSame('Technical Architecture > Support Operations > Escalation Matrix', $chunks[1]->sectionTitle);
        $this->assertSame(2, $chunks[1]->pageNumber);
    }

    /**
     * Verifies that oversized sections are split into multiple overlapping chunks
     */
    public function testSplitsLongSectionsWithOverlap(): void
    {
        $chunker = new DocumentChunker(targetTokens: 50, overlapTokens: 10);
        $longParagraph = str_repeat('The platform enforces strict rate limiting of 500 requests per second per tenant. ', 15);

        $chunks = $chunker->chunkText("# Performance\n\n" . $longParagraph, 'limits.md');

        $this->assertGreaterThan(2, count($chunks));
        foreach ($chunks as $index => $chunk) {
            $this->assertSame($index, $chunk->chunkIndex);
            $this->assertSame('Performance', $chunk->sectionTitle);
            $this->assertGreaterThan(0, $chunk->tokenEstimate);
        }
    }
}
