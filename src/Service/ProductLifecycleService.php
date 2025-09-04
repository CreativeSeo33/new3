<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\Manufacturer;
use App\Entity\ProductOptionValueAssignment;
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

    protected function materializeEffectivePrice(Product $product): void
    {
        if ($product->isVariable()) {
            // Вариативный товар: обнуляем основные цены и вычисляем effectivePrice
            $this->handleVariableProduct($product);
        } else {
            // Простой товар: используем основную цену
            $this->handleSimpleProduct($product);
        }
    }

    protected function handleVariableProduct(Product $product): void
    {
        // Обнуляем основные цены для вариативного товара
        $product->setPrice(null);
        $product->setSalePrice(null);
        $product->setQuantity(null);

        // Вычисляем минимальную цену среди всех вариаций
        $minPrice = null;
        /** @var ProductOptionValueAssignment $assignment */
        foreach ($product->getOptionAssignments() as $assignment) {
            $currentPrice = $assignment->getSalePrice() ?? $assignment->getPrice();
            if ($currentPrice !== null && ($minPrice === null || $currentPrice < $minPrice)) {
                $minPrice = $currentPrice;
            }
        }

        $product->setEffectivePrice($minPrice);
    }

    protected function handleSimpleProduct(Product $product): void
    {
        // Для простого товара effectivePrice равна основной цене
        $effectivePrice = $product->getSalePrice() ?? $product->getPrice();
        $product->setEffectivePrice($effectivePrice);
    }
}


