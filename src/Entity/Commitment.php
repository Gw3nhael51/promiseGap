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

    /**
     * Initializes a new commitment entity with its creation timestamp
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Returns unique identifier of the commitment
     *
     * @return int|null Commitment identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns parent analysis workspace
     *
     * @return Analysis|null Parent analysis entity
     */
    public function getAnalysis(): ?Analysis
    {
        return $this->analysis;
    }

    /**
     * Associates the commitment with an analysis workspace
     *
     * @param Analysis|null $analysis Parent analysis entity
     * @return static Current commitment instance
     */
    public function setAnalysis(?Analysis $analysis): static
    {
        $this->analysis = $analysis;

        return $this;
    }

    /**
     * Returns exact verbatim text of the commercial commitment
     *
     * @return string|null Verbatim commitment statement
     */
    public function getVerbatimText(): ?string
    {
        return $this->verbatimText;
    }

    /**
     * Sets exact verbatim text of the commercial commitment
     *
     * @param string $verbatimText Verbatim commitment statement
     * @return static Current commitment instance
     */
    public function setVerbatimText(string $verbatimText): static
    {
        $this->verbatimText = $verbatimText;

        return $this;
    }

    /**
     * Returns functional category of the commitment
     *
     * @return string Category label
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Sets functional category of the commitment
     *
     * @param string $category Category label
     * @return static Current commitment instance
     */
    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Returns AI extraction confidence score between zero and one
     *
     * @return float Extraction confidence score
     */
    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    /**
     * Sets AI extraction confidence score
     *
     * @param float $confidenceScore Confidence score between zero and one
     * @return static Current commitment instance
     */
    public function setConfidenceScore(float $confidenceScore): static
    {
        $this->confidenceScore = $confidenceScore;

        return $this;
    }

    /**
     * Returns source page number in the proposal document
     *
     * @return int|null One-indexed page number
     */
    public function getSourcePage(): ?int
    {
        return $this->sourcePage;
    }

    /**
     * Sets source page number in the proposal document
     *
     * @param int|null $sourcePage One-indexed page number
     * @return static Current commitment instance
     */
    public function setSourcePage(?int $sourcePage): static
    {
        $this->sourcePage = $sourcePage;

        return $this;
    }

    /**
     * Returns creation timestamp of the commitment record
     *
     * @return \DateTimeImmutable Creation timestamp
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets creation timestamp of the commitment record
     *
     * @param \DateTimeImmutable $createdAt Creation timestamp
     * @return static Current commitment instance
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns associated discrepancy qualification finding
     *
     * @return Finding|null Associated finding entity
     */
    public function getFinding(): ?Finding
    {
        return $this->finding;
    }

    /**
     * Associates a discrepancy qualification finding with this commitment
     *
     * @param Finding|null $finding Associated finding entity
     * @return static Current commitment instance
     */
    public function setFinding(?Finding $finding): static
    {
        if ($finding !== null && $finding->getCommitment() !== $this) {
            $finding->setCommitment($this);
        }

        $this->finding = $finding;

        return $this;
    }
}
