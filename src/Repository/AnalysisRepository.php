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

    /**
     * Returns a clamped, paginated slice of analyses ordered by creation date descending
     *
     * @param string|null $ownerIdentifier Optional owner email filter (null for all analyses)
     * @param int $page Requested 1-based page number
     * @param int $perPage Maximum items per page
     * @return array{items: Analysis[], currentPage: int, totalPages: int, totalItems: int, perPage: int, hasPrev: bool, hasNext: bool}
     */
    public function findPaginated(?string $ownerIdentifier = null, int $page = 1, int $perPage = 9): array
    {
        $perPage = max(1, $perPage);
        $criteria = $ownerIdentifier !== null ? ['ownerIdentifier' => $ownerIdentifier] : [];

        $totalItems = $this->count($criteria);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = max(1, min($page, $totalPages));
        $offset = ($currentPage - 1) * $perPage;

        /** @var Analysis[] $items */
        $items = $this->findBy($criteria, ['createdAt' => 'DESC', 'id' => 'DESC'], $perPage, $offset);

        return [
            'items' => $items,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'perPage' => $perPage,
            'hasPrev' => $currentPage > 1,
            'hasNext' => $currentPage < $totalPages,
        ];
    }
}
