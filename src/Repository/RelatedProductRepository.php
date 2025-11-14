<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\RelatedProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class RelatedProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RelatedProduct::class);
    }

    /**
     * @return Product[]
     */
    public function findRelatedProducts(int $productId, int $limit = 10): array
    {
        $links = $this->createQueryBuilder('relatedProductLink')
            ->innerJoin('relatedProductLink.relatedProduct', 'relatedProduct')
            ->addSelect('relatedProduct')
            ->andWhere('IDENTITY(relatedProductLink.product) = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('relatedProductLink.sortOrder', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (RelatedProduct $relatedProductLink): Product => $relatedProductLink->getRelatedProduct(),
            $links
        );
    }
}


