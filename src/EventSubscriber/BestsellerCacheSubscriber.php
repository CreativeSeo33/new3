<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Bestseller;
use App\Service\BestsellerService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class BestsellerCacheSubscriber
{
    public function __construct(
        private BestsellerService $bestsellerService
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateCacheIfBestseller($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateCacheIfBestseller($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateCacheIfBestseller($args->getObject());
    }

    private function invalidateCacheIfBestseller(object $entity): void
    {
        if ($entity instanceof Bestseller) {
            $this->bestsellerService->invalidateCache();
        }
    }
}


