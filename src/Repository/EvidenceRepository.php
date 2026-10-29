<?php

namespace App\Repository;

use App\Entity\Evidence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for verifiable source Evidence entities
 *
 * @extends ServiceEntityRepository<Evidence>
 */
class EvidenceRepository extends ServiceEntityRepository
{
    /**
     * Initializes the evidence repository
     *
     * @param ManagerRegistry $registry Doctrine persistence manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evidence::class);
    }
}
