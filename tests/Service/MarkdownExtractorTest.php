<?php

namespace App\Tests\Service;

use App\Exception\TextExtractionException;
use App\Service\MarkdownExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Validates Markdown and plain text extraction
 */
class MarkdownExtractorTest extends TestCase
{
    /**
     * Writes content to a temporary file
     *
     * @param string $content File content
     * @return string Absolute path
     */
    private function write(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'md_');
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Verifies that headers and bullet points are preserved
     */
    public function testPreservesHeadersAndBullets(): void
    {
        $path = $this->write("# SLA\r\n\r\n## Availability\r\n- 99.5% monthly\r\n- Credits apply\r\n");

        $result = (new MarkdownExtractor())->extract($path);

        $this->assertSame("# SLA\n\n## Availability\n- 99.5% monthly\n- Credits apply", $result->text);
        $this->assertSame(1, $result->pageCount);
        @unlink($path);
    }

    /**
     * Verifies that BOM, null bytes and excessive blank lines are removed
     */
    public function testCleansUnsafeCharacters(): void
    {
        $path = $this->write("\xEF\xBB\xBFTitle\0\n\n\n\n\nBody   \n");

        $result = (new MarkdownExtractor())->extract($path);

        $this->assertSame("Title\n\nBody", $result->text);
        @unlink($path);
    }

    /**
     * Verifies that an empty document is reported as an extraction error
     */
    public function testEmptyDocumentFails(): void
    {
        $path = $this->write("  \n\n ");

        $this->expectException(TextExtractionException::class);

        try {
            (new MarkdownExtractor())->extract($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Verifies that token estimates follow the four characters per token rule
     */
    public function testEstimatesTokens(): void
    {
        $path = $this->write(str_repeat('a', 41));

        $result = (new MarkdownExtractor())->extract($path);

        $this->assertSame(41, $result->characterCount());
        $this->assertSame(11, $result->estimatedTokens());
        @unlink($path);
    }
}
