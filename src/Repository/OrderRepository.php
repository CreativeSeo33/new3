<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class OrderRepository extends ServiceEntityRepository
{
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
}



