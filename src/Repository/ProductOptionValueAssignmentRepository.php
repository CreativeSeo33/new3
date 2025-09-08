<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductOptionValueAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductOptionValueAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductOptionValueAssignment::class);
    }
}


