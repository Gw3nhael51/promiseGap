<?php

namespace App\Entity;

use App\Enum\AnalysisStatus;
use App\Repository\AnalysisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a contract commitment audit analysis workspace
 */
#[ORM\Entity(repositoryClass: AnalysisRepository::class)]
#[ORM\Table(name: 'analyses')]
#[ORM\HasLifecycleCallbacks]
class Analysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50, enumType: AnalysisStatus::class)]
    private AnalysisStatus $status = AnalysisStatus::PENDING;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $ownerIdentifier = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'analysis', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    /**
     * @var Collection<int, Commitment>
     */
    #[ORM\OneToMany(targetEntity: Commitment::class, mappedBy: 'analysis', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $commitments;

    /**
     * Initializes a new analysis instance with default values
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->documents = new ArrayCollection();
        $this->commitments = new ArrayCollection();
        $this->status = AnalysisStatus::PENDING;
    }

    /**
     * Returns unique identifier of the analysis
     *
     * @return int|null Analysis identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns title of the analysis workspace
     *
     * @return string|null Workspace title
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Sets title of the analysis workspace
     *
     * @param string $title New workspace title
     * @return static Current instance
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Returns identifier of the user owning the analysis (null for shared demo analyses)
     *
     * @return string|null Owner user identifier
     */
    public function getOwnerIdentifier(): ?string
    {
        return $this->ownerIdentifier;
    }

    /**
     * Sets identifier of the user owning the analysis
     *
     * @param string|null $ownerIdentifier Owner user identifier
     * @return static Current instance
     */
    public function setOwnerIdentifier(?string $ownerIdentifier): static
    {
        $this->ownerIdentifier = $ownerIdentifier;

        return $this;
    }

    /**
     * Indicates whether the analysis is a shared demo analysis without owner
     *
     * @return bool True when no owner is attached
     */
    public function isDemo(): bool
    {
        return $this->ownerIdentifier === null;
    }

    /**
     * Returns current status of the analysis
     *
     * @return AnalysisStatus Current status
     */
    public function getStatus(): AnalysisStatus
    {
        return $this->status;
    }

    /**
     * Sets status of the analysis
     *
     * @param AnalysisStatus $status New status
     * @return static Current instance
     */
    public function setStatus(AnalysisStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Returns creation date of the analysis
     *
     * @return \DateTimeImmutable|null Creation timestamp
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets creation date of the analysis
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
     * Returns last update date
     *
     * @return \DateTimeImmutable|null Last update timestamp
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Sets last update date
     *
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     * @return static Current instance
     */
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Automatically updates the modification timestamp before update
     *
     * @return void
     */
    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Returns collection of attached documents
     *
     * @return Collection<int, Document> Attached documents collection
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    /**
     * Adds an attached document to the analysis
     *
     * @param Document $document Document to attach
     * @return static Current instance
     */
    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setAnalysis($this);
        }

        return $this;
    }

    /**
     * Removes an attached document from the analysis
     *
     * @param Document $document Document to detach
     * @return static Current instance
     */
    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getAnalysis() === $this) {
                $document->setAnalysis(null);
            }
        }

        return $this;
    }

    /**
     * Returns all commercial commitments extracted for this analysis
     *
     * @return Collection<int, Commitment>
     */
    public function getCommitments(): Collection
    {
        return $this->commitments;
    }

    /**
     * Attaches a commercial commitment to this analysis
     *
     * @param Commitment $commitment Commercial commitment entity to attach
     * @return static Current instance
     */
    public function addCommitment(Commitment $commitment): static
    {
        if (!$this->commitments->contains($commitment)) {
            $this->commitments->add($commitment);
            $commitment->setAnalysis($this);
        }

        return $this;
    }

    /**
     * Detaches a commercial commitment from this analysis
     *
     * @param Commitment $commitment Commercial commitment entity to detach
     * @return static Current instance
     */
    public function removeCommitment(Commitment $commitment): static
    {
        if ($this->commitments->removeElement($commitment)) {
            if ($commitment->getAnalysis() === $this) {
                $commitment->setAnalysis(null);
            }
        }

        return $this;
    }
}
