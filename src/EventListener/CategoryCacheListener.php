<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Category;
use App\Service\BreadcrumbBuilder;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Category::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Category::class)]
final class CategoryCacheListener
{
    public function __construct(private readonly BreadcrumbBuilder $breadcrumbBuilder)
    {
    }

    public function postUpdate(Category $category, LifecycleEventArgs $event): void
    {
        $this->breadcrumbBuilder->clearCacheForCategory($category);
    }

    public function postRemove(Category $category, LifecycleEventArgs $event): void
    {
        $this->breadcrumbBuilder->clearCacheForCategory($category);
    }
}


