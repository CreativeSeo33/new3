<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use App\ApiResource\ProductResource;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\PaginationService;

/**
 * Provides ProductResource items and collections from Product entity.
 */
class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductRepository $repository,
        private readonly PaginationService $pagination
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductResource|array|PaginatorInterface|null
    {
        $isCollection = method_exists($operation, 'getClass') === false || ($context['operation_name'] ?? '') === 'api_products_get_collection' || str_contains(get_class($operation), 'GetCollection');
        if ($isCollection) {
            $filters = $context['filters'] ?? [];
            $page = max(1, (int)($filters['page'] ?? 1));
            $itemsPerPage = $this->pagination->normalizeItemsPerPage((int)($filters['itemsPerPage'] ?? $this->pagination->getDefaultItemsPerPage()));

            $offset = ($page - 1) * $itemsPerPage;

            $qb = $this->repository->createQueryBuilder('p')
                ->setFirstResult($offset)
                ->setMaxResults($itemsPerPage);

            // Apply name filter (partial match)
            $name = (string)($filters['name'] ?? '');
            $name = trim($name);
            if ($name !== '') {
                $qb->andWhere('LOWER(p.name) LIKE :name')
                   ->setParameter('name', '%' . strtolower($name) . '%');
            }

            // Apply category filter (exact by category id)
            $categoryId = $filters['category'] ?? null;
            if ($categoryId !== null && $categoryId !== '') {
                $qb->leftJoin('p.category', 'pc')
                   ->leftJoin('pc.category', 'c')
                   ->andWhere('c.id = :cid')
                   ->setParameter('cid', (int)$categoryId);
            }

            // Apply ordering via API Platform-style filters: order[dateAdded], order[status]
            $order = $filters['order'] ?? [];
            $createdDir = strtoupper((string)($order['dateAdded'] ?? '')) === 'ASC' ? 'ASC' : (strtoupper((string)($order['dateAdded'] ?? '')) === 'DESC' ? 'DESC' : null);
            $statusDir = strtoupper((string)($order['status'] ?? '')) === 'ASC' ? 'ASC' : (strtoupper((string)($order['status'] ?? '')) === 'DESC' ? 'DESC' : null);

            $applied = false;
            if ($createdDir !== null) {
                // Embedded field: timestamps.createdAt maps to date_added
                $qb->orderBy('p.timestamps.createdAt', $createdDir);
                $applied = true;
            }
            if ($statusDir !== null) {
                if ($applied) {
                    $qb->addOrderBy('p.status', $statusDir);
                } else {
                    $qb->orderBy('p.status', $statusDir);
                    $applied = true;
                }
            }
            if (!$applied) {
                // Default order
                $qb->orderBy('p.id', 'DESC');
            }

            $entities = $qb->getQuery()->getResult();
            $resources = array_map([$this, 'transformLightweight'], $entities);

            // Count with filters applied
            $countQb = $this->repository->createQueryBuilder('p');
            // Reapply filters
            if ($name !== '') {
                $countQb->andWhere('LOWER(p.name) LIKE :name')->setParameter('name', '%' . strtolower($name) . '%');
            }
            if ($categoryId !== null && $categoryId !== '') {
                $countQb->leftJoin('p.category', 'pc')
                    ->leftJoin('pc.category', 'c')
                    ->andWhere('c.id = :cid')
                    ->setParameter('cid', (int)$categoryId);
            }
            $totalItems = (int)$countQb->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

            return new TraversablePaginator(new \ArrayIterator($resources), $page, $itemsPerPage, $totalItems);
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
        $r->optionsJson = $entity->getOptionsJson();
        $r->manufacturerId = $entity->getManufacturerRef()?->getId();
        $r->manufacturerName = $entity->getManufacturerRef()?->getName();
        $r->createdAt = $entity->getDateAdded();
        // categories (names)
        $r->categoryNames = [];
        foreach ($entity->getCategory() as $pc) {
            $name = $pc->getCategory()?->getName();
            if ($name !== null) {
                $r->categoryNames[] = $name;
            }
        }
        // images
        $r->image = [];
        foreach ($entity->getImage() as $img) {
            $r->image[] = [
                'id' => $img->getId(),
                'imageUrl' => $img->getImageUrl(),
                'sortOrder' => $img->getSortOrder(),
            ];
        }
        return $r;
    }

    /**
     * Lightweight transform for collection payload (firstImageUrl only)
     */
    private function transformLightweight(Product $entity): ProductResource
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
        $r->manufacturerId = $entity->getManufacturerRef()?->getId();
        $r->manufacturerName = $entity->getManufacturerRef()?->getName();
        $r->sortOrder = $entity->getSortOrder();
        $r->createdAt = $entity->getDateAdded();
        $r->categoryNames = [];
        foreach ($entity->getCategory() as $pc) {
            $name = $pc->getCategory()?->getName();
            if ($name !== null) {
                $r->categoryNames[] = $name;
            }
        }
        $firstImage = $entity->getImage()->first();
        $r->firstImageUrl = $firstImage ? $firstImage->getImageUrl() : null;
        $r->image = [];
        return $r;
    }
}


