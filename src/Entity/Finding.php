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

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->evidences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommitment(): ?Commitment
    {
        return $this->commitment;
    }

    public function setCommitment(?Commitment $commitment): static
    {
        $this->commitment = $commitment;

        return $this;
    }

    public function getStatus(): FindingStatus
    {
        return $this->status;
    }

    public function setStatus(FindingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRationale(): ?string
    {
        return $this->rationale;
    }

    public function setRationale(string $rationale): static
    {
        $this->rationale = $rationale;

        return $this;
    }

    public function isVerifiedByHuman(): bool
    {
        return $this->isVerifiedByHuman;
    }

    public function setIsVerifiedByHuman(bool $isVerifiedByHuman): static
    {
        $this->isVerifiedByHuman = $isVerifiedByHuman;

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

    /**
     * @return Collection<int, Evidence>
     */
    public function getEvidences(): Collection
    {
        return $this->evidences;
    }

    public function addEvidence(Evidence $evidence): static
    {
        if (!$this->evidences->contains($evidence)) {
            $this->evidences->add($evidence);
            $evidence->setFinding($this);
        }

        return $this;
    }

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
