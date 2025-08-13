<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @return Product[]
     */
    public function findActiveByCategory(Category $category, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->distinct()
            ->innerJoin('p.category', 'pc')
            ->leftJoin('p.image', 'img')->addSelect('img')
            ->andWhere('pc.category = :category')
            ->andWhere('pc.visibility = true')
            ->andWhere('p.status = true')
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('img.sortOrder', 'ASC')
            ->setParameter('category', $category)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}


