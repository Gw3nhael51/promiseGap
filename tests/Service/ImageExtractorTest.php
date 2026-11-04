<?php

namespace App\Tests\Service;

use App\Entity\Document;
use App\Enum\DocumentType;
use App\Enum\ExtractionStatus;
use App\Exception\TextExtractionException;
use App\Service\CachedAiService;
use App\Service\DocumentUploader;
use App\Service\ImageExtractor;
use App\Service\MarkdownExtractor;
use App\Service\MetaAiClient;
use App\Service\PdfExtractor;
use App\Service\TextExtractionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Validates multimodal document image extraction, SHA-256 caching, and zero-image-retention cleanup
 */
class ImageExtractorTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;

    /**
     * Prepares an isolated temporary directory before each test
     */
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/promisegap_img_test_' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->tempDir);
    }

    /**
     * Removes temporary test files after each test
     */
    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    /**
     * Creates a valid 1x1 PNG file on disk
     *
     * @param string $filename Target filename inside the temporary directory
     * @return string Absolute path to the created PNG file
     */
    private function createValidPng(string $filename = 'scan.png'): string
    {
        $path = $this->tempDir . '/' . $filename;
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true,
        );
        file_put_contents($path, $pngBytes);

        return $path;
    }

    /**
     * Verifies that ImageExtractor transcribes a valid PNG document image and caches by image SHA-256
     */
    public function testExtractValidPngAndCacheByImageSha256(): void
    {
        $metaClient = new MetaAiClient(new MockHttpClient(), new NullLogger(), '');
        $cachePool = new ArrayAdapter();
        $cachedAi = new CachedAiService($metaClient, $cachePool, new NullLogger(), true, 50.0);
        $extractor = new ImageExtractor($cachedAi);

        $firstImagePath = $this->createValidPng('contract_page_1.png');
        $secondImagePath = $this->createValidPng('re_uploaded_copy.png');

        $result = $extractor->extract($firstImagePath);

        $this->assertStringStartsWith('[Page 1]', $result->text);
        $this->assertStringContainsString('99.95%', $result->text);
        $this->assertSame(1, $result->pageCount);

        $keyA = $cachedAi->computeCacheKey('sys', 'usr', ['image_path' => $firstImagePath]);
        $keyB = $cachedAi->computeCacheKey('sys', 'usr', ['image_path' => $secondImagePath]);
        $this->assertSame($keyA, $keyB, 'Identical image contents at different paths must produce the same SHA-256 cache key.');
    }

    /**
     * Verifies that TextExtractionService extracts text from an uploaded image and immediately deletes the physical image file
     */
    public function testProcessExtractsTextAndPurgesEphemeralImageFile(): void
    {
        $uploader = new DocumentUploader($this->tempDir, new AsciiSlugger(), $this->filesystem);
        $metaClient = new MetaAiClient(new MockHttpClient(), new NullLogger(), '');
        $imageExtractor = new ImageExtractor($metaClient);

        $service = new TextExtractionService(
            $uploader,
            new PdfExtractor(),
            new MarkdownExtractor(),
            $imageExtractor,
        );

        $sourcePng = $this->createValidPng('source_invoice.png');
        $uploadedFile = new UploadedFile($sourcePng, 'invoice_scan.png', 'image/png', null, true);
        $document = $uploader->upload($uploadedFile, DocumentType::PROPOSAL);

        $storedFilePath = $this->tempDir . '/' . $document->getStoragePath();
        $this->assertFileExists($storedFilePath);

        $service->process($document);

        $this->assertSame(ExtractionStatus::DONE, $document->getExtractionStatus());
        $this->assertNotEmpty($document->getExtractedText());
        $this->assertGreaterThan(10, $document->getCharacterCount());
        $this->assertFileDoesNotExist($storedFilePath, 'Physical image file must be purged immediately after extraction (Zero-Image-Retention).');
    }

    /**
     * Verifies that a corrupted non-image file is rejected with a TextExtractionException
     */
    public function testCorruptedImageThrowsException(): void
    {
        $metaClient = new MetaAiClient(new MockHttpClient(), new NullLogger(), '');
        $extractor = new ImageExtractor($metaClient);

        $badPath = $this->tempDir . '/corrupted.png';
        file_put_contents($badPath, 'not an image');

        $this->expectException(TextExtractionException::class);
        $extractor->extract($badPath);
    }
}
