<?php
declare(strict_types=1);

namespace App\Doctrine\Subscriber;

use App\Entity\Product;
use App\Service\Search\ProductIndexer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;

#[AsDoctrineListener(event: 'postPersist')]
#[AsDoctrineListener(event: 'postUpdate')]
#[AsDoctrineListener(event: 'postRemove')]
final class ProductSearchIndexerSubscriber
{
    public function __construct(private readonly ProductIndexer $indexer)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Product) {
            $this->indexer->upsert($entity);
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Product) {
            $this->indexer->upsert($entity);
        }
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Product) {
            $id = $entity->getId();
            if ($id !== null) {
                $this->indexer->delete($id);
            }
        }
    }
}


