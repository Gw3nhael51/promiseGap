<?php

namespace App\Entity;

use App\Repository\EvidenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a verifiable source passage supporting or contradicting a commitment
 */
#[ORM\Entity(repositoryClass: EvidenceRepository::class)]
#[ORM\Table(name: 'evidences')]
class Evidence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Finding::class, inversedBy: 'evidences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Finding $finding = null;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Document $document = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $quote = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sectionTitle = null;

    #[ORM\Column(nullable: true)]
    private ?int $pageNumber = 1;

    #[ORM\Column(type: Types::FLOAT)]
    private float $similarityScore = 0.0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Initializes a new evidence entity with its creation timestamp
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Returns unique identifier of the evidence record
     *
     * @return int|null Evidence identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns parent finding evaluated by this evidence
     *
     * @return Finding|null Parent finding entity
     */
    public function getFinding(): ?Finding
    {
        return $this->finding;
    }

    /**
     * Associates the evidence citation with its parent finding
     *
     * @param Finding|null $finding Parent finding entity
     * @return static Current evidence instance
     */
    public function setFinding(?Finding $finding): static
    {
        $this->finding = $finding;

        return $this;
    }

    /**
     * Returns reference specification document from which the quote was retrieved
     *
     * @return Document|null Source reference document
     */
    public function getDocument(): ?Document
    {
        return $this->document;
    }

    /**
     * Sets reference specification document from which the quote was retrieved
     *
     * @param Document|null $document Source reference document
     * @return static Current evidence instance
     */
    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    /**
     * Returns verbatim quote extracted from the reference specification
     *
     * @return string|null Verbatim specification quote
     */
    public function getQuote(): ?string
    {
        return $this->quote;
    }

    /**
     * Sets verbatim quote extracted from the reference specification
     *
     * @param string $quote Verbatim specification quote
     * @return static Current evidence instance
     */
    public function setQuote(string $quote): static
    {
        $this->quote = $quote;

        return $this;
    }

    /**
     * Returns section heading where the quote appears in the specification
     *
     * @return string|null Section heading title
     */
    public function getSectionTitle(): ?string
    {
        return $this->sectionTitle;
    }

    /**
     * Sets section heading where the quote appears in the specification
     *
     * @param string|null $sectionTitle Section heading title
     * @return static Current evidence instance
     */
    public function setSectionTitle(?string $sectionTitle): static
    {
        $this->sectionTitle = $sectionTitle;

        return $this;
    }

    /**
     * Returns page number where the quote appears in the specification
     *
     * @return int|null One-indexed page number
     */
    public function getPageNumber(): ?int
    {
        return $this->pageNumber;
    }

    /**
     * Sets page number where the quote appears in the specification
     *
     * @param int|null $pageNumber One-indexed page number
     * @return static Current evidence instance
     */
    public function setPageNumber(?int $pageNumber): static
    {
        $this->pageNumber = $pageNumber;

        return $this;
    }

    /**
     * Returns cosine similarity score between the commitment and retrieved chunk
     *
     * @return float Cosine similarity score
     */
    public function getSimilarityScore(): float
    {
        return $this->similarityScore;
    }

    /**
     * Sets cosine similarity score between the commitment and retrieved chunk
     *
     * @param float $similarityScore Cosine similarity score
     * @return static Current evidence instance
     */
    public function setSimilarityScore(float $similarityScore): static
    {
        $this->similarityScore = $similarityScore;

        return $this;
    }

    /**
     * Returns creation timestamp of the evidence record
     *
     * @return \DateTimeImmutable Creation timestamp
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets creation timestamp of the evidence record
     *
     * @param \DateTimeImmutable $createdAt Creation timestamp
     * @return static Current evidence instance
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
