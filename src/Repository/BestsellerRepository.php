<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bestseller;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class BestsellerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bestseller::class);
    }

    /**
     * Получить Product-объекты из Bestsellers, отсортированные по sortOrder.
     *
     * @return Product[]
     */
    public function getBestsellersWithProducts(): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('b, p, img')
            ->innerJoin('b.product', 'p')
            ->leftJoin('p.image', 'img')
            ->andWhere('p.status = :status')
            ->setParameter('status', true)
            ->orderBy('b.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        // Преобразуем результат к массиву Product[]
        $products = [];
        foreach ($results as $row) {
            if (\is_array($row)) {
                // Ожидаемо: [0 => Bestseller, 1 => Product]
                $products[] = $row[1];
            } elseif ($row instanceof Bestseller) {
                $product = $row->getProduct();
                if ($product instanceof Product) {
                    $products[] = $product;
                }
            }
        }

        return $products;
    }
}

