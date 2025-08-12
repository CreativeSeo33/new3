<?php
declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Product;
use App\Service\ProductLifecycleService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;

#[AsEntityListener(event: Events::prePersist, entity: Product::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Product::class)]
class ProductEntityListener
{
    public function __construct(private readonly ProductLifecycleService $lifecycleService)
    {
    }

    public function prePersist(Product $product, PrePersistEventArgs $event): void
    {
        $this->lifecycleService->handlePrePersist($product);
    }

    public function preUpdate(Product $product, PreUpdateEventArgs $event): void
    {
        $this->lifecycleService->handlePreUpdate($product);

        // Ensure Doctrine is aware of changes made in listener
        $om = $event->getObjectManager();
        if ($om instanceof EntityManagerInterface) {
            $meta = $om->getClassMetadata(Product::class);
            $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $product);
        }
    }
}


