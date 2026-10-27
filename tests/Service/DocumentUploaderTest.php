<?php

namespace App\Tests\Service;

use App\Enum\DocumentType;
use App\Service\DocumentUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Validates document upload behavior and constraint verification
 */
class DocumentUploaderTest extends TestCase
{
    private string $testUploadDir;
    private Filesystem $filesystem;
    private DocumentUploader $uploader;

    /**
     * Prepares isolated temporary directory and uploader instance
     */
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->testUploadDir = sys_get_temp_dir() . '/promisegap_test_uploads_' . bin2hex(random_bytes(4));
        $this->filesystem->mkdir($this->testUploadDir);

        $this->uploader = new DocumentUploader(
            $this->testUploadDir,
            new AsciiSlugger(),
            $this->filesystem
        );
    }

    /**
     * Cleans up temporary files and directories after each test
     */
    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->testUploadDir)) {
            $this->filesystem->remove($this->testUploadDir);
        }
    }

    /**
     * Verifies successful upload and entity creation for a valid PDF document
     */
    public function testUploadValidPdf(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tempFile, '%PDF-1.4 test content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'commercial_proposal.pdf',
            'application/pdf',
            null,
            true
        );

        $document = $this->uploader->upload($uploadedFile, DocumentType::PROPOSAL);

        $this->assertSame('commercial_proposal.pdf', $document->getOriginalFilename());
        $this->assertSame(DocumentType::PROPOSAL, $document->getDocumentType());
        $this->assertSame('application/pdf', $document->getMimeType());
        $this->assertNotEmpty($document->getStoragePath());
        $this->assertFileExists($this->testUploadDir . '/' . $document->getStoragePath());
    }

    /**
     * Verifies successful upload and entity creation for a valid Markdown document
     */
    public function testUploadValidMarkdown(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_md_');
        file_put_contents($tempFile, '# Technical Specifications');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'specs.md',
            'text/markdown',
            null,
            true
        );

        $document = $this->uploader->upload($uploadedFile, DocumentType::TECHNICAL_SPEC);

        $this->assertSame('specs.md', $document->getOriginalFilename());
        $this->assertSame(DocumentType::TECHNICAL_SPEC, $document->getDocumentType());
        $this->assertFileExists($this->testUploadDir . '/' . $document->getStoragePath());
    }

    /**
     * Verifies that files exceeding 10 MB are rejected with an InvalidArgumentException
     */
    public function testUploadRejectsOversizedFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_large_');
        $fileHandle = fopen($tempFile, 'w');
        fseek($fileHandle, 10485761); // 10 MB + 1 byte
        fwrite($fileHandle, '0');
        fclose($fileHandle);

        $uploadedFile = new UploadedFile(
            $tempFile,
            'huge.pdf',
            'application/pdf',
            null,
            true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds the maximum allowed limit of 10 MB.');

        $this->uploader->upload($uploadedFile, DocumentType::PROPOSAL);
    }

    /**
     * Verifies that unauthorized file extensions and MIME types are rejected
     */
    public function testUploadRejectsUnauthorizedFormat(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_exe_');
        file_put_contents($tempFile, 'binary content');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'malware.exe',
            'application/x-msdownload',
            null,
            true
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file format. Allowed formats: PDF and Markdown.');

        $this->uploader->upload($uploadedFile, DocumentType::PROPOSAL);
    }

    /**
     * Verifies that remove deletes the corresponding physical file on disk
     */
    public function testRemoveDeletesPhysicalFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_delete_');
        file_put_contents($tempFile, 'file to delete');

        $uploadedFile = new UploadedFile(
            $tempFile,
            'temp.md',
            'text/plain',
            null,
            true
        );

        $document = $this->uploader->upload($uploadedFile, DocumentType::TECHNICAL_SPEC);
        $filePath = $this->testUploadDir . '/' . $document->getStoragePath();
        $this->assertFileExists($filePath);

        $this->uploader->remove($document);
        $this->assertFileDoesNotExist($filePath);
    }
}
