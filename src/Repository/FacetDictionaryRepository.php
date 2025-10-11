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

    public function findByCategoryIdOrCreate(int $categoryId): FacetDictionary
    {
        $facetDictionary = $this->findByCategoryId($categoryId);
        
        if (!$facetDictionary) {
            $facetDictionary = new FacetDictionary();
            $category = $this->getEntityManager()->getReference(Category::class, $categoryId);
            $facetDictionary->setCategory($category);
        }
        
        return $facetDictionary;
    }

    public function findByCategoryOrCreate(Category $category): FacetDictionary
    {
        $facetDictionary = $this->findByCategory($category);
        
        if (!$facetDictionary) {
            $facetDictionary = new FacetDictionary();
            $facetDictionary->setCategory($category);
        }
        
        return $facetDictionary;
    }

    public function findAllByPriceRange(int $minPrice, int $maxPrice): array
    {
        return $this->createQueryBuilder('fd')
            ->where('fd.priceMin <= :maxPrice')
            ->andWhere('fd.priceMax >= :minPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->getQuery()
            ->getResult();
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