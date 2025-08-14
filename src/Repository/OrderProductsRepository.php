<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderProducts;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class OrderProductsRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, OrderProducts::class);
	}
}



