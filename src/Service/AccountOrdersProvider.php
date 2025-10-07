<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AccountOrdersProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Возвращает заказы текущего пользователя со связанными сущностями и картой продуктов для изображений.
     * @return array{orders: Order[], productMap: array<int, Product>}
     */
    public function getOrdersWithProductMap(User $user, int $limit = 50, int $offset = 0): array
    {
        try {
            $limit = max(1, min(100, $limit));
            $offset = max(0, $offset);

            $qb = $this->em->createQueryBuilder()
                ->select('o', 'oc', 'od', 'op')
                ->from(Order::class, 'o')
                ->leftJoin('o.customer', 'oc')
                ->leftJoin('o.delivery', 'od')
                ->leftJoin('o.products', 'op')
                ->where('o.user = :user')
                ->setParameter('user', $user)
                ->orderBy('o.dateAdded', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            /** @var Order[] $orders */
            $orders = $qb->getQuery()->getResult();

            $productIds = [];
            foreach ($orders as $order) {
                foreach ($order->getProducts() as $op) {
                    $pid = $op->getProductId();
                    if (is_numeric($pid)) {
                        $productIds[] = (int)$pid;
                    }
                }
            }
            $productIds = array_values(array_unique($productIds));

            $productMap = [];
            if (count($productIds) > 0) {
                $qb = $this->em->createQueryBuilder()
                    ->select('p', 'img')
                    ->from(Product::class, 'p')
                    ->leftJoin('p.image', 'img')
                    ->where('p.id IN (:ids)')
                    ->setParameter('ids', $productIds)
                    ->addOrderBy('img.sortOrder', 'ASC');
                /** @var Product[] $products */
                $products = $qb->getQuery()->getResult();
                foreach ($products as $p) {
                    $productMap[$p->getId()] = $p;
                }
            }

            return [
                'orders' => $orders,
                'productMap' => $productMap,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('AccountOrdersProvider failed to load orders', [
                'exception' => $e,
            ]);
            throw new \RuntimeException('Failed to load orders');
        }
    }
}


