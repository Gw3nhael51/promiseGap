<?php

namespace App\Entity;

use App\Enum\DocumentType;
use App\Enum\ExtractionStatus;
use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a file attached to an analysis workspace
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 255)]
    private ?string $storagePath = null;

    #[ORM\Column(length: 100)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private ?int $fileSize = null;

    #[ORM\Column(length: 50, enumType: DocumentType::class)]
    private DocumentType $documentType = DocumentType::PROPOSAL;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $extractedText = null;

    #[ORM\Column(length: 20, enumType: ExtractionStatus::class)]
    private ExtractionStatus $extractionStatus = ExtractionStatus::PENDING;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $extractionError = null;

    #[ORM\Column(nullable: true)]
    private ?int $characterCount = null;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedTokens = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Analysis::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Analysis $analysis = null;

    /**
     * Initializes a new document instance with current timestamp
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Returns unique identifier of the document
     *
     * @return int|null Document identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns original filename of the uploaded file
     *
     * @return string|null Original filename
     */
    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    /**
     * Sets original filename of the uploaded file
     *
     * @param string $originalFilename Original filename
     * @return static Current instance
     */
    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    /**
     * Returns internal storage path of the file
     *
     * @return string|null Relative or absolute storage path
     */
    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    /**
     * Sets internal storage path of the file
     *
     * @param string $storagePath Storage path
     * @return static Current instance
     */
    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;

        return $this;
    }

    /**
     * Returns MIME type of the file
     *
     * @return string|null MIME type
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Sets MIME type of the file
     *
     * @param string $mimeType Validated MIME type
     * @return static Current instance
     */
    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Returns file size in bytes
     *
     * @return int|null File size in bytes
     */
    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    /**
     * Sets file size in bytes
     *
     * @param int $fileSize File size in bytes
     * @return static Current instance
     */
    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    /**
     * Returns functional document type
     *
     * @return DocumentType Document type enum
     */
    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    /**
     * Sets functional document type
     *
     * @param DocumentType $documentType Document type enum
     * @return static Current instance
     */
    public function setDocumentType(DocumentType $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    /**
     * Returns extracted text content
     *
     * @return string|null Extracted text content
     */
    public function getExtractedText(): ?string
    {
        return $this->extractedText;
    }

    /**
     * Sets extracted text content
     *
     * @param string|null $extractedText Extracted text content
     * @return static Current instance
     */
    public function setExtractedText(?string $extractedText): static
    {
        $this->extractedText = $extractedText;

        return $this;
    }

    /**
     * Returns upload creation timestamp
     *
     * @return \DateTimeImmutable|null Creation timestamp
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets upload creation timestamp
     *
     * @param \DateTimeImmutable $createdAt Creation timestamp
     * @return static Current instance
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns parent analysis workspace
     *
     * @return Analysis|null Parent analysis
     */
    public function getAnalysis(): ?Analysis
    {
        return $this->analysis;
    }

    /**
     * Sets parent analysis workspace
     *
     * @param Analysis|null $analysis Parent analysis
     * @return static Current instance
     */
    public function setAnalysis(?Analysis $analysis): static
    {
        $this->analysis = $analysis;

        return $this;
    }

    /**
     * Returns the text extraction status
     *
     * @return ExtractionStatus Extraction status
     */
    public function getExtractionStatus(): ExtractionStatus
    {
        return $this->extractionStatus;
    }

    /**
     * Sets the text extraction status
     *
     * @param ExtractionStatus $extractionStatus New extraction status
     * @return static Current instance
     */
    public function setExtractionStatus(ExtractionStatus $extractionStatus): static
    {
        $this->extractionStatus = $extractionStatus;

        return $this;
    }

    /**
     * Returns the reason why the extraction failed
     *
     * @return string|null Extraction error message
     */
    public function getExtractionError(): ?string
    {
        return $this->extractionError;
    }

    /**
     * Sets the reason why the extraction failed
     *
     * @param string|null $extractionError Extraction error message
     * @return static Current instance
     */
    public function setExtractionError(?string $extractionError): static
    {
        $this->extractionError = $extractionError;

        return $this;
    }

    /**
     * Returns the number of extracted characters
     *
     * @return int|null Character count
     */
    public function getCharacterCount(): ?int
    {
        return $this->characterCount;
    }

    /**
     * Sets the number of extracted characters
     *
     * @param int|null $characterCount Character count
     * @return static Current instance
     */
    public function setCharacterCount(?int $characterCount): static
    {
        $this->characterCount = $characterCount;

        return $this;
    }

    /**
     * Returns the estimated number of tokens of the extracted text
     *
     * @return int|null Estimated token count
     */
    public function getEstimatedTokens(): ?int
    {
        return $this->estimatedTokens;
    }

    /**
     * Sets the estimated number of tokens of the extracted text
     *
     * @param int|null $estimatedTokens Estimated token count
     * @return static Current instance
     */
    public function setEstimatedTokens(?int $estimatedTokens): static
    {
        $this->estimatedTokens = $estimatedTokens;

        return $this;
    }
}
