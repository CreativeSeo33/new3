<?php
declare(strict_types=1);

namespace App\Doctrine\Subscriber;

use App\Entity\Product;
use App\Entity\ProductToCategory;
use App\Entity\ProductAttributeAssignment;
use App\Entity\ProductOptionValueAssignment;
use App\EventSubscriber\FacetReindexSubscriber as KernelFacetSubscriber;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

final class FacetDoctrineSubscriber implements EventSubscriber
{
    public function __construct(private readonly KernelFacetSubscriber $kernelSubscriber) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void { $this->collect($args); }
    public function postUpdate(LifecycleEventArgs $args): void { $this->collect($args); }
    public function postRemove(LifecycleEventArgs $args): void { $this->collect($args); }

    private function collect(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $ref = new \ReflectionClass($this->kernelSubscriber);
        $method = $ref->getMethod('collect');
        $method->setAccessible(true);
        $method->invoke($this->kernelSubscriber, $args);
    }
}
