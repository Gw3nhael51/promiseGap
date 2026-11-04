<?php

namespace App\Tests\Service;

use App\Exception\TextExtractionException;
use App\Service\PdfExtractor;
use App\Tests\Support\PdfFactory;
use PHPUnit\Framework\TestCase;

/**
 * Validates PDF text extraction with pdftotext
 */
class PdfExtractorTest extends TestCase
{
    /**
     * Verifies that the text of each page is extracted with page markers
     */
    public function testExtractsTextPageByPage(): void
    {
        $path = PdfFactory::write(['Availability target is 99.99 percent', 'Support response time is four hours']);

        $result = (new PdfExtractor())->extract($path);

        $this->assertSame(2, $result->pageCount);
        $this->assertStringContainsString("[Page 1]\nAvailability target is 99.99 percent", $result->text);
        $this->assertStringContainsString("[Page 2]\nSupport response time is four hours", $result->text);
        @unlink($path);
    }

    /**
     * Verifies that a PDF without text layer reports a clear scanned document error
     */
    public function testImageOnlyPdfIsReportedAsNotExtractable(): void
    {
        $path = PdfFactory::write(['']);

        $this->expectException(TextExtractionException::class);
        $this->expectExceptionMessage('no extractable text');

        try {
            (new PdfExtractor())->extract($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Verifies that a corrupted PDF fails gracefully
     */
    public function testCorruptedPdfFails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad_') . '.pdf';
        file_put_contents($path, '%PDF-1.4 this is not a real pdf');

        $this->expectException(TextExtractionException::class);

        try {
            (new PdfExtractor())->extract($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * Verifies that a missing file fails gracefully
     */
    public function testMissingFileFails(): void
    {
        $this->expectException(TextExtractionException::class);

        (new PdfExtractor())->extract('/nonexistent/file.pdf');
    }
}
