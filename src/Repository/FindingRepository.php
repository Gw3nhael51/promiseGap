<?php

namespace App\Repository;

use App\Entity\Finding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for discrepancy Finding entities
 *
 * @extends ServiceEntityRepository<Finding>
 */
class FindingRepository extends ServiceEntityRepository
{
    /**
     * Initializes the finding repository
     *
     * @param ManagerRegistry $registry Doctrine persistence manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Finding::class);
    }
}
