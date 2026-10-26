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
}
