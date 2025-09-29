<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\FacetConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class FacetConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FacetConfig::class);
    }

    public function findEffectiveConfigForCategory(int $categoryId): ?FacetConfig
    {
        // Try category-specific first
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.category', 'cat')
            ->andWhere('c.scope = :scopeCat')
            ->andWhere('cat.id = :cid')
            ->setParameter('scopeCat', FacetConfig::SCOPE_CATEGORY)
            ->setParameter('cid', $categoryId)
            ->setMaxResults(1);

        $categoryConfig = $qb->getQuery()->getOneOrNullResult();
        if ($categoryConfig instanceof FacetConfig) {
            return $categoryConfig;
        }

        // Fallback to global
        return $this->createQueryBuilder('c2')
            ->andWhere('c2.scope = :scopeGlobal')
            ->setParameter('scopeGlobal', FacetConfig::SCOPE_GLOBAL)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}


