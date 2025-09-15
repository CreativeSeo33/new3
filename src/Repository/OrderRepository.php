<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class OrderRepository extends ServiceEntityRepository
{
    /**
     * AI-META v1
     * role: Репозиторий заказов; генерация порядкового orderId (историческая реализация)
     * module: Order
     * dependsOn:
     *   - Doctrine\Persistence\ManagerRegistry
     * invariants:
     *   - getNextOrderId = MAX(orderId)+1 (подвержено гонкам; для production рекомендована последовательность)
     * transaction: none
     * tests:
     *   - TODO: Добавить тест на конкурирующие вызовы getNextOrderId
     * lastUpdated: 2025-09-15
     */
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Order::class);
	}

	public function save(Order $entity, bool $flush = false): void
	{
		$this->_em->persist($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	public function remove(Order $entity, bool $flush = false): void
	{
		$this->_em->remove($entity);
		if ($flush) {
			$this->_em->flush();
		}
	}

	public function getNextOrderId(): int
	{
		$qb = $this->createQueryBuilder('o')
			->select('MAX(o.orderId) as maxId');
		$max = (int)($qb->getQuery()->getSingleScalarResult() ?? 0);
		return $max + 1;
	}
}



