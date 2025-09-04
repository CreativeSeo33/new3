<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

final class CartRepository extends ServiceEntityRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Cart::class);
	}

	public function findActiveByUser(int $userId): ?Cart
	{
		return $this->createQueryBuilder('c')
			->andWhere('c.userId = :u')
			->andWhere('c.expiresAt IS NULL OR c.expiresAt > CURRENT_TIMESTAMP()')
			->setParameter('u', $userId)
			->orderBy('c.updatedAt', 'DESC')
			->setMaxResults(1)
			->getQuery()->getOneOrNullResult();
	}

	public function findActiveByToken(string $token): ?Cart
	{
		return $this->createQueryBuilder('c')
			->andWhere('c.token = :t')
			->andWhere('c.expiresAt IS NULL OR c.expiresAt > CURRENT_TIMESTAMP()')
			->setParameter('t', $token)
			->setMaxResults(1)
			->getQuery()->getOneOrNullResult();
	}

	public function findActiveById(Ulid $id): ?Cart
	{
		return $this->createQueryBuilder('c')
			->andWhere('c.id = :id')
			->andWhere('c.expiresAt IS NULL OR c.expiresAt > CURRENT_TIMESTAMP()')
			->setParameter('id', $id, 'ulid')
			->setMaxResults(1)
			->getQuery()->getOneOrNullResult();
	}

	public function findActiveByUserId(int $userId): ?Cart
	{
		return $this->createQueryBuilder('c')
			->andWhere('c.userId = :u')
			->andWhere('c.expiresAt IS NULL OR c.expiresAt > CURRENT_TIMESTAMP()')
			->setParameter('u', $userId)
			->orderBy('c.updatedAt', 'DESC')
			->setMaxResults(1)
			->getQuery()->getOneOrNullResult();
	}

    public function findItemForUpdate(Cart $cart, Product $product): ?CartItem
    {
        return $this->getEntityManager()->createQuery(
            'SELECT ci FROM App\\Entity\\CartItem ci WHERE ci.cart = :c AND ci.product = :p AND ci.optionsHash IS NULL'
        )
        ->setParameters(['c' => $cart, 'p' => $product])
        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
        ->setMaxResults(1)
        ->getOneOrNullResult();
    }

    public function findItemForUpdateWithOptions(Cart $cart, Product $product, string $optionsHash): ?CartItem
    {
        return $this->getEntityManager()->createQuery(
            'SELECT ci FROM App\\Entity\\CartItem ci WHERE ci.cart = :c AND ci.product = :p AND ci.optionsHash = :h'
        )
        ->setParameters(['c' => $cart, 'p' => $product, 'h' => $optionsHash])
        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
        ->setMaxResults(1)
        ->getOneOrNullResult();
    }

    public function findItemByIdForUpdate(Cart $cart, int $itemId): ?CartItem
    {
        return $this->getEntityManager()->createQuery(
            'SELECT ci FROM App\\Entity\\CartItem ci WHERE ci.cart = :c AND ci.id = :i'
        )
        ->setParameters(['c' => $cart, 'i' => $itemId])
        ->setLockMode(LockMode::PESSIMISTIC_WRITE)
        ->setMaxResults(1)
        ->getOneOrNullResult();
    }
}


