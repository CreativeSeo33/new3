<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Entity\WishlistItem;
use App\Repository\WishlistItemRepository;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;

class WishlistManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private WishlistRepository $wishlists,
        private WishlistItemRepository $items
    ) {}

    public function createForGuest(string $token): Wishlist
    {
        $w = new Wishlist();
        $w->setToken($token);
        $w->setExpiresAt((new \DateTimeImmutable())->modify('+365 days'));
        $this->em->persist($w);
        $this->em->flush();
        return $w;
    }

    public function createForUser(User $user): Wishlist
    {
        $w = new Wishlist();
        $w->setUser($user);
        $this->em->persist($w);
        $this->em->flush();
        return $w;
    }

    public function addItem(Wishlist $w, Product $p): void
    {
        foreach ($w->getItems() as $it) {
            if ($it->getProduct()->getId() === $p->getId()) return; // dedup
        }
        $item = new WishlistItem($w, $p);
        $w->addItem($item);
        $this->em->persist($item);
        $w->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function removeItem(Wishlist $w, Product $p): void
    {
        foreach ($w->getItems() as $it) {
            if ($it->getProduct()->getId() === $p->getId()) {
                $w->removeItem($it);
                $this->em->remove($it);
                break;
            }
        }
        $w->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function clear(Wishlist $w): void
    {
        foreach ($w->getItems() as $it) {
            $this->em->remove($it);
        }
        $w->getItems()->clear();
        $w->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function merge(Wishlist $to, Wishlist $from): Wishlist
    {
        foreach ($from->getItems() as $it) {
            $exists = false;
            foreach ($to->getItems() as $t) {
                if ($t->getProduct()->getId() === $it->getProduct()->getId()) { $exists = true; break; }
            }
            if (!$exists) {
                $clone = new WishlistItem($to, $it->getProduct());
                $to->addItem($clone);
                $this->em->persist($clone);
            }
        }
        $to->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();
        return $to;
    }

    public function touch(Wishlist $w, bool $prolong = true): void
    {
        $w->setUpdatedAt(new \DateTimeImmutable());
        if ($prolong && $w->getToken()) {
            $w->setExpiresAt((new \DateTimeImmutable())->modify('+365 days'));
        }
        $this->em->flush();
    }
}


