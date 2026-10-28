<?php

namespace App\Service;

use App\Entity\Document;
use App\Enum\DocumentType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Handles secure file uploads and storage for analysis documents
 */
class DocumentUploader
{
    private const int MAX_FILE_SIZE = 10485760; // 10 MB

    private const array ALLOWED_MIME_TYPES = [
        'application/pdf',
        'text/plain',
        'text/markdown',
        'text/x-markdown',
    ];

    private const array ALLOWED_EXTENSIONS = [
        'pdf',
        'md',
        'markdown',
        'txt',
    ];

    private const array EXTENSION_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'md' => ['text/plain', 'text/markdown', 'text/x-markdown'],
        'markdown' => ['text/plain', 'text/markdown', 'text/x-markdown'],
        'txt' => ['text/plain'],
    ];

    /**
     * Initializes the document upload service
     *
     * @param string $targetDirectory Directory path where uploaded files are stored
     * @param SluggerInterface $slugger Utility to sanitize uploaded filenames
     * @param Filesystem $filesystem Filesystem utility for directory and file operations
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/uploads/documents')]
        private string $targetDirectory,
        private SluggerInterface $slugger,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * Uploads a file, validates constraints and creates the corresponding Document entity
     *
     * @param UploadedFile $file The uploaded file from the HTTP request
     * @param DocumentType $documentType Functional classification of the document
     * @return Document Hydrated Document entity ready for persistence
     * @throws \InvalidArgumentException When file format, extension or size exceeds limits
     * @throws FileException When file cannot be moved to destination directory
     */
    public function upload(UploadedFile $file, DocumentType $documentType): Document
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('The file upload failed. Please try again.');
        }

        $fileSize = $file->getSize();
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File size exceeds the maximum allowed limit of 10 MB.');
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (!in_array($clientExtension, self::ALLOWED_EXTENSIONS, true)
            || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)
            || !in_array($mimeType, self::EXTENSION_MIME_TYPES[$clientExtension], true)
        ) {
            throw new \InvalidArgumentException('Unsupported file format. Allowed formats: PDF and Markdown.');
        }

        if ($clientExtension === 'pdf' && !$this->hasPdfSignature($file)) {
            throw new \InvalidArgumentException('Unsupported file format. Allowed formats: PDF and Markdown.');
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension() ?? $clientExtension;
        if (empty($extension)) {
            $extension = 'bin';
        }
        $newFilename = sprintf('%s-%s.%s', $safeFilename, bin2hex(random_bytes(8)), $extension);

        if (!$this->filesystem->exists($this->targetDirectory)) {
            $this->filesystem->mkdir($this->targetDirectory);
        }

        $file->move($this->targetDirectory, $newFilename);

        $document = new Document();
        $document->setOriginalFilename(mb_substr(basename($file->getClientOriginalName()), 0, 255));
        $document->setStoragePath($newFilename);
        $document->setMimeType($mimeType);
        $document->setFileSize($fileSize);
        $document->setDocumentType($documentType);

        return $document;
    }

    /**
     * Deletes the physical file associated with a document
     *
     * @param Document $document Document entity whose physical file should be deleted
     * @return void
     */
    public function remove(Document $document): void
    {
        $storagePath = $document->getStoragePath();
        if ($storagePath === null) {
            return;
        }

        $fullPath = $this->targetDirectory . '/' . basename($storagePath);
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
        }
    }

    /**
     * Returns the absolute path of the target storage directory
     *
     * @return string Directory path
     */
    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    /**
     * Checks that a file starts with the PDF magic number
     *
     * @param UploadedFile $file Uploaded file to inspect
     * @return bool True when the file header is a valid PDF signature
     */
    private function hasPdfSignature(UploadedFile $file): bool
    {
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }
}
