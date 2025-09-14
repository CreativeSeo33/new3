<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fias;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fias>
 */
class FiasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fias::class);
    }

    /**
     * Найти записи по почтовому индексу
     */
    public function findByPostalcode(string $postalcode): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.postalcode = :postalcode')
            ->setParameter('postalcode', $postalcode)
            ->orderBy('f.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти записи по названию
     */
    public function findByName(string $name, ?int $level = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.offname LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('f.level', 'ASC');

        if ($level !== null) {
            $qb->andWhere('f.level = :level')
               ->setParameter('level', $level);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти дочерние записи
     */
    public function findChildren(int $parentId, ?int $level = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.parentId = :parentId')
            ->setParameter('parentId', $parentId)
            ->orderBy('f.offname', 'ASC');

        if ($level !== null) {
            $qb->andWhere('f.level = :level')
               ->setParameter('level', $level);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти по уровню
     */
    public function findByLevel(int $level): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.level = :level')
            ->setParameter('level', $level)
            ->orderBy('f.offname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Найти регионы (уровень 1)
     */
    public function findRegions(): array
    {
        return $this->findByLevel(1);
    }

    /**
     * Найти города (уровень 3)
     */
    public function findCities(?int $regionId = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.level = 3')
            ->orderBy('f.offname', 'ASC');

        if ($regionId !== null) {
            $qb->andWhere('f.parentId = :regionId')
               ->setParameter('regionId', $regionId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти населенные пункты (уровень 4)
     */
    public function findSettlements(?int $cityId = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.level = 4')
            ->orderBy('f.offname', 'ASC');

        if ($cityId !== null) {
            $qb->andWhere('f.parentId = :cityId')
               ->setParameter('cityId', $cityId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Найти улицы (уровень 5)
     */
    public function findStreets(?int $settlementId = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.level = 5')
            ->orderBy('f.offname', 'ASC');

        if ($settlementId !== null) {
            $qb->andWhere('f.parentId = :settlementId')
               ->setParameter('settlementId', $settlementId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Получить полный путь до записи
     */
    public function getFullPath(Fias $fias): array
    {
        $path = [];
        $current = $fias;

        while ($current->getParentId() !== 0) {
            $path[] = $current;
            $current = $this->find($current->getParentId());

            if (!$current) {
                break;
            }
        }

        // Добавить страну, если не добавлена
        if ($current && $current->getParentId() === 0) {
            $path[] = $current;
        }

        return array_reverse($path);
    }
}
