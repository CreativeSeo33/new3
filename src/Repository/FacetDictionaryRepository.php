<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FacetDictionary;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FacetDictionary>
 */
class FacetDictionaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FacetDictionary::class);
    }

    public function findByCategoryId(int $categoryId): ?FacetDictionary
    {
        return $this->findOneBy(['category' => $categoryId]);
    }

    public function findByCategory(Category $category): ?FacetDictionary
    {
        return $this->findOneBy(['category' => $category]);
    }

    public function findByPriceRange(int $minPrice, int $maxPrice): array
    {
        return $this->createQueryBuilder('fd')
            ->where('fd.priceMin <= :maxPrice')
            ->andWhere('fd.priceMax >= :minPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->getQuery()
            ->getResult();
    }

    public function findByAttributes(array $attributes): array
    {
        $qb = $this->createQueryBuilder('fd');
        
        foreach ($attributes as $key => $value) {
            $qb->andWhere("JSON_CONTAINS(fd.attributesJson, :attr_{$key}) = 1")
               ->setParameter("attr_{$key}", json_encode([$key => $value]));
        }
        
        return $qb->getQuery()->getResult();
    }

    public function findByOptions(array $options): array
    {
        $qb = $this->createQueryBuilder('fd');
        
        foreach ($options as $key => $value) {
            $qb->andWhere("JSON_CONTAINS(fd.optionsJson, :opt_{$key}) = 1")
               ->setParameter("opt_{$key}", json_encode([$key => $value]));
        }
        
        return $qb->getQuery()->getResult();
    }

    public function save(FacetDictionary $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FacetDictionary $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
