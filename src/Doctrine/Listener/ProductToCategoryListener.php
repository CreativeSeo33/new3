<?php
declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\ProductToCategory;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class ProductToCategoryListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ProductToCategory) {
            return;
        }

        $this->populateRelations($entity, $args->getObjectManager());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ProductToCategory) {
            return;
        }

        $this->populateRelations($entity, $args->getObjectManager());
    }

    private function populateRelations(ProductToCategory $entity, $entityManager): void
    {
        // Populate product relation from productId
        if ($entity->getProductId() && !$entity->getProduct()) {
            $product = $entityManager->getRepository(\App\Entity\Product::class)->find($entity->getProductId());
            if ($product) {
                $entity->setProduct($product);
            }
        }

        // Populate category relation from categoryId
        if ($entity->getCategoryId() && !$entity->getCategory()) {
            $category = $entityManager->getRepository(\App\Entity\Category::class)->find($entity->getCategoryId());
            if ($category) {
                $entity->setCategory($category);
            }
        }
    }
}
