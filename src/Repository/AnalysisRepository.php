<?php

namespace App\Repository;

use App\Entity\Analysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Manages queries and data access for Analysis entities
 *
 * @extends ServiceEntityRepository<Analysis>
 */
class AnalysisRepository extends ServiceEntityRepository
{
    /**
     * Initializes repository with Doctrine manager registry
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Analysis::class);
    }

    /**
     * Returns the most recent analyses owned by a given user
     *
     * @param string $ownerIdentifier Owner user identifier
     * @param int|null $limit Maximum number of results
     * @return Analysis[] Analyses ordered by creation date descending
     */
    public function findByOwner(string $ownerIdentifier, ?int $limit = null): array
    {
        return $this->findBy(['ownerIdentifier' => $ownerIdentifier], ['createdAt' => 'DESC'], $limit);
    }
}
