<?php

namespace App\Repository;

use App\Entity\Commitment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for commercial Commitment entities
 *
 * @extends ServiceEntityRepository<Commitment>
 */
class CommitmentRepository extends ServiceEntityRepository
{
    /**
     * Initializes the commitment repository
     *
     * @param ManagerRegistry $registry Doctrine persistence manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commitment::class);
    }
}
