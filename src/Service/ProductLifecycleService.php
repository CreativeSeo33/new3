<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\Manufacturer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductLifecycleService
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function handlePrePersist(Product $product): void
    {
        // Code
        $product->setCode();

        // Dates
        $product->setDateAdded();

        // Slug
        $product->ensureSlug();

        // Manufacturer sync
        $this->syncManufacturer($product);

        // Effective price materialization
        $this->materializeEffectivePrice($product);
    }

    public function handlePreUpdate(Product $product): void
    {
        // Update timestamp
        $product->touchUpdatedAt();

        // Ensure slug still set if name changed
        $product->ensureSlug();

        // Manufacturer sync
        $this->syncManufacturer($product);

        // Effective price materialization
        $this->materializeEffectivePrice($product);
    }

    private function syncManufacturer(Product $product): void
    {
        // Ensure reference consistency (no legacy int syncing anymore)
        if ($product->getManufacturerRef() instanceof Manufacturer) {
            // nothing else to do
        }
    }

    private function materializeEffectivePrice(Product $product): void
    {
        $product->setEffectivePrice($product->getEffectivePrice());
    }
}


