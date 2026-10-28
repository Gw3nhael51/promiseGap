<?php

namespace App\Entity;

use App\Repository\CommitmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a commercial commitment or promise extracted from a proposal document
 */
#[ORM\Entity(repositoryClass: CommitmentRepository::class)]
#[ORM\Table(name: 'commitments')]
class Commitment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Analysis::class, inversedBy: 'commitments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Analysis $analysis = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $verbatimText = null;

    #[ORM\Column(length: 100)]
    private string $category = 'GENERAL';

    #[ORM\Column(type: Types::FLOAT)]
    private float $confidenceScore = 1.0;

    #[ORM\Column(nullable: true)]
    private ?int $sourcePage = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToOne(targetEntity: Finding::class, mappedBy: 'commitment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Finding $finding = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnalysis(): ?Analysis
    {
        return $this->analysis;
    }

    public function setAnalysis(?Analysis $analysis): static
    {
        $this->analysis = $analysis;

        return $this;
    }

    public function getVerbatimText(): ?string
    {
        return $this->verbatimText;
    }

    public function setVerbatimText(string $verbatimText): static
    {
        $this->verbatimText = $verbatimText;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(float $confidenceScore): static
    {
        $this->confidenceScore = $confidenceScore;

        return $this;
    }

    public function getSourcePage(): ?int
    {
        return $this->sourcePage;
    }

    public function setSourcePage(?int $sourcePage): static
    {
        $this->sourcePage = $sourcePage;

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

    public function getFinding(): ?Finding
    {
        return $this->finding;
    }

    public function setFinding(?Finding $finding): static
    {
        if ($finding !== null && $finding->getCommitment() !== $this) {
            $finding->setCommitment($this);
        }

        $this->finding = $finding;

        return $this;
    }
}
