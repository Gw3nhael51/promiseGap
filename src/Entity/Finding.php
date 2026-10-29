<?php

namespace App\Entity;

use App\Enum\FindingStatus;
use App\Repository\FindingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents the qualification verdict comparing a commitment against reference specifications
 */
#[ORM\Entity(repositoryClass: FindingRepository::class)]
#[ORM\Table(name: 'findings')]
class Finding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Commitment::class, inversedBy: 'finding')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commitment $commitment = null;

    #[ORM\Column(length: 30, enumType: FindingStatus::class)]
    private FindingStatus $status = FindingStatus::AMBIGUOUS;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $rationale = null;

    #[ORM\Column]
    private bool $isVerifiedByHuman = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Evidence>
     */
    #[ORM\OneToMany(targetEntity: Evidence::class, mappedBy: 'finding', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $evidences;

    /**
     * Initializes a new finding entity with an empty evidence collection
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->evidences = new ArrayCollection();
    }

    /**
     * Returns unique identifier of the finding
     *
     * @return int|null Finding identifier
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns evaluated commercial commitment
     *
     * @return Commitment|null Evaluated commitment entity
     */
    public function getCommitment(): ?Commitment
    {
        return $this->commitment;
    }

    /**
     * Associates the finding with its evaluated commercial commitment
     *
     * @param Commitment|null $commitment Evaluated commitment entity
     * @return static Current finding instance
     */
    public function setCommitment(?Commitment $commitment): static
    {
        $this->commitment = $commitment;

        return $this;
    }

    /**
     * Returns discrepancy qualification status
     *
     * @return FindingStatus Qualification verdict enum
     */
    public function getStatus(): FindingStatus
    {
        return $this->status;
    }

    /**
     * Sets discrepancy qualification status
     *
     * @param FindingStatus $status Qualification verdict enum
     * @return static Current finding instance
     */
    public function setStatus(FindingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Returns AI-generated reasoning explaining the qualification verdict
     *
     * @return string|null Qualification rationale text
     */
    public function getRationale(): ?string
    {
        return $this->rationale;
    }

    /**
     * Sets AI-generated reasoning explaining the qualification verdict
     *
     * @param string $rationale Qualification rationale text
     * @return static Current finding instance
     */
    public function setRationale(string $rationale): static
    {
        $this->rationale = $rationale;

        return $this;
    }

    /**
     * Indicates whether a human reviewer has confirmed this finding
     *
     * @return bool True when human-verified
     */
    public function isVerifiedByHuman(): bool
    {
        return $this->isVerifiedByHuman;
    }

    /**
     * Updates the human verification flag on this finding
     *
     * @param bool $isVerifiedByHuman Human verification flag
     * @return static Current finding instance
     */
    public function setIsVerifiedByHuman(bool $isVerifiedByHuman): static
    {
        $this->isVerifiedByHuman = $isVerifiedByHuman;

        return $this;
    }

    /**
     * Returns creation timestamp of the finding record
     *
     * @return \DateTimeImmutable Creation timestamp
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets creation timestamp of the finding record
     *
     * @param \DateTimeImmutable $createdAt Creation timestamp
     * @return static Current finding instance
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns collection of supporting or contradicting evidence citations
     *
     * @return Collection<int, Evidence> Evidence collection
     */
    public function getEvidences(): Collection
    {
        return $this->evidences;
    }

    /**
     * Attaches an evidence citation to this finding
     *
     * @param Evidence $evidence Evidence entity to attach
     * @return static Current finding instance
     */
    public function addEvidence(Evidence $evidence): static
    {
        if (!$this->evidences->contains($evidence)) {
            $this->evidences->add($evidence);
            $evidence->setFinding($this);
        }

        return $this;
    }

    /**
     * Detaches an evidence citation from this finding
     *
     * @param Evidence $evidence Evidence entity to detach
     * @return static Current finding instance
     */
    public function removeEvidence(Evidence $evidence): static
    {
        if ($this->evidences->removeElement($evidence)) {
            if ($evidence->getFinding() === $this) {
                $evidence->setFinding(null);
            }
        }

        return $this;
    }
}
