<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Product;
use App\Entity\ProductToCategory;
use App\Entity\ProductAttributeAssignment;
use App\Entity\ProductOptionValueAssignment;
use App\Service\FacetIndexer;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class FacetReindexSubscriber implements EventSubscriberInterface
{
    /** @var array<int,true> */
    private array $pendingCategoryIds = [];

    public function __construct(private readonly FacetIndexer $indexer) {}

    public static function getSubscribedEvents(): array
    {
        // Bridge for Symfony EventSubscriberInterface
        return [KernelEvents::TERMINATE => 'onKernelTerminate'];
    }

    public function postPersist(LifecycleEventArgs $args): void { $this->collect($args); }
    public function postUpdate(LifecycleEventArgs $args): void { $this->collect($args); }
    public function postRemove(LifecycleEventArgs $args): void { $this->collect($args); }

    public function collect(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof ProductToCategory) {
            $cid = $entity->getCategoryId();
            if ($cid) { $this->pendingCategoryIds[$cid] = true; }
        } elseif ($entity instanceof ProductAttributeAssignment || $entity instanceof ProductOptionValueAssignment) {
            $om = $args->getObjectManager();
            $product = method_exists($entity, 'getProduct') ? $entity->getProduct() : null;
            if ($product instanceof Product) {
                $rows = [];
                if ($om instanceof \Doctrine\ORM\EntityManagerInterface) {
                    $rows = $om->createQueryBuilder()
                        ->select('IDENTITY(r.category) AS cid')
                        ->from(ProductToCategory::class, 'r')
                        ->andWhere('r.product = :p')
                        ->setParameter('p', $product)
                        ->getQuery()->getScalarResult();
                }
                foreach ($rows as $row) {
                    $cid = (int)($row['cid'] ?? 0);
                    if ($cid) { $this->pendingCategoryIds[$cid] = true; }
                }
            }
        } elseif ($entity instanceof Product) {
            // Product status/price changes affect all its categories
            $rows = [];
            $om = $args->getObjectManager();
            if ($om instanceof \Doctrine\ORM\EntityManagerInterface) {
                $rows = $om->createQueryBuilder()
                    ->select('IDENTITY(r.category) AS cid')
                    ->from(ProductToCategory::class, 'r')
                    ->andWhere('r.product = :p')
                    ->setParameter('p', $entity)
                    ->getQuery()->getScalarResult();
            }
            foreach ($rows as $row) {
                $cid = (int)($row['cid'] ?? 0);
                if ($cid) { $this->pendingCategoryIds[$cid] = true; }
            }
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $unique = array_keys($this->pendingCategoryIds);
        $this->pendingCategoryIds = [];
        foreach ($unique as $cid) {
            $this->indexer->reindexCategory((int)$cid);
        }
    }
}


