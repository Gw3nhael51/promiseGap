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

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFinding(): ?Finding
    {
        return $this->finding;
    }

    public function setFinding(?Finding $finding): static
    {
        $this->finding = $finding;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getQuote(): ?string
    {
        return $this->quote;
    }

    public function setQuote(string $quote): static
    {
        $this->quote = $quote;

        return $this;
    }

    public function getSectionTitle(): ?string
    {
        return $this->sectionTitle;
    }

    public function setSectionTitle(?string $sectionTitle): static
    {
        $this->sectionTitle = $sectionTitle;

        return $this;
    }

    public function getPageNumber(): ?int
    {
        return $this->pageNumber;
    }

    public function setPageNumber(?int $pageNumber): static
    {
        $this->pageNumber = $pageNumber;

        return $this;
    }

    public function getSimilarityScore(): float
    {
        return $this->similarityScore;
    }

    public function setSimilarityScore(float $similarityScore): static
    {
        $this->similarityScore = $similarityScore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
