<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Wishlist;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Wishlist>
 */
class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    public function findByToken(string $token): ?Wishlist
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findByUser(?User $user): ?Wishlist
    {
        if (!$user) return null;
        return $this->findOneBy(['user' => $user]);
    }
}


