<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\PvzPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PvzPrice>
 */
final class PvzPriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PvzPrice::class);
    }

    public function findOneByCityNormalized(string $cityName): ?PvzPrice
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('LOWER(TRIM(p.city)) = :city')
            ->setParameter('city', mb_strtolower(trim($cityName)))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}


