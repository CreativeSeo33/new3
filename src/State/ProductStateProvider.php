<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\ProductResource;
use App\Entity\Product;
use App\Repository\ProductRepository;

/**
 * Provides ProductResource items and collections from Product entity.
 */
class ProductStateProvider implements ProviderInterface
{
    public function __construct(private readonly ProductRepository $repository)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductResource|array|null
    {
        $isCollection = method_exists($operation, 'getClass') === false || ($context['operation_name'] ?? '') === 'api_products_get_collection' || str_contains(get_class($operation), 'GetCollection');
        if ($isCollection) {
            $entities = $this->repository->findBy([], ['id' => 'DESC']);
            return array_map([$this, 'transform'], $entities);
        }

        $id = $uriVariables['id'] ?? null;
        if ($id === null) {
            return null;
        }
        $entity = $this->repository->find((int) $id);
        return $entity ? $this->transform($entity) : null;
    }

    private function transform(Product $entity): ProductResource
    {
        $r = new ProductResource();
        $r->id = $entity->getId();
        $r->code = $entity->getCode()?->toRfc4122();
        $r->name = $entity->getName();
        $r->slug = $entity->getSlug();
        $r->price = $entity->getPrice();
        $r->salePrice = $entity->getSalePrice();
        $r->effectivePrice = $entity->getEffectivePrice();
        $r->status = $entity->getStatus();
        $r->quantity = $entity->getQuantity();
        $r->sortOrder = $entity->getSortOrder();
        $r->description = $entity->getDescription();
        $r->metaTitle = $entity->getMetaTitle();
        $r->metaDescription = $entity->getMetaDescription();
        $r->metaKeywords = $entity->getMetaKeywords();
        $r->h1 = $entity->getMetaH1();
        $r->manufacturerId = $entity->getManufacturerRef()?->getId();
        $r->manufacturerName = $entity->getManufacturerRef()?->getName();
        return $r;
    }
}


