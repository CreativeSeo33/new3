<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * Check if product is used in any cart items
     */
    public function isProductUsedInCarts(Product $product): bool
    {
        $count = $this->createQueryBuilder('ci')
            ->select('COUNT(ci.id)')
            ->where('ci.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Remove all cart items for a specific product
     */
    public function removeCartItemsForProduct(Product $product): int
    {
        return $this->createQueryBuilder('ci')
            ->delete()
            ->where('ci.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->execute();
    }
}



