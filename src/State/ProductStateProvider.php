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
use App\Service\Search\ProductSearch;
use App\Service\PaginationService;

/**
 * Provides ProductResource items and collections from Product entity.
 *
 * AI-META v1
 * role: Поставщик данных для ProductResource (коллекции/одиночные), backend-пагинация и фильтры
 * module: Admin
 * dependsOn:
 *   - App\Repository\ProductRepository
 *   - App\Service\PaginationService
 * invariants:
 *   - Пагинация и фильтры применяются на уровне Doctrine (не во Vue)
 * transaction: none
 * lastUpdated: 2025-09-15
 */
class ProductStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductRepository $repository,
        private readonly PaginationService $pagination,
        private readonly ProductSearch $search,
        private readonly string $searchEngine = ''
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

            // q-поиск через TNTSearch при включённом движке
            $q = trim((string)($filters['q'] ?? ''));
            $useTnt = ($this->searchEngine === 'tnt') && $q !== '';

            if ($useTnt) {
                $found = $this->search->search($q, $itemsPerPage, $offset);
                $ids = $found['ids'];
                $totalItems = $found['total'];
                if (empty($ids)) {
                    return new TraversablePaginator(new \ArrayIterator([]), $page, $itemsPerPage, 0);
                }

                // Загружаем сущности с сохранением порядка
                $entities = $this->repository->createQueryBuilder('p')
                    ->andWhere('p.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->getQuery()->getResult();
                // map by id
                $byId = [];
                foreach ($entities as $e) { $byId[$e->getId()] = $e; }
                $ordered = [];
                foreach ($ids as $id) { if (isset($byId[$id])) { $ordered[] = $byId[$id]; } }
                $resources = array_map([$this, 'transformLightweight'], $ordered);
                return new TraversablePaginator(new \ArrayIterator($resources), $page, $itemsPerPage, $totalItems);
            }

            $qb = $this->repository->createQueryBuilder('p')
                ->setFirstResult($offset)
                ->setMaxResults($itemsPerPage);

            // Имевшийся name-фильтр остаётся для mysql-движка
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

            // Compute optionsCount for current page in one grouped query (only for variable products)
            $ids = array_map(static fn($e) => $e->getId(), $entities);
            $optionsCountById = [];
            if (!empty($ids)) {
                $connQb = $this->repository->createQueryBuilder('p2')
                    ->select('p2.id AS id, COUNT(oa.id) AS cnt')
                    ->leftJoin('p2.optionAssignments', 'oa')
                    ->andWhere('p2.id IN (:ids)')
                    ->andWhere('p2.type = :vtype')
                    ->setParameter('ids', $ids)
                    ->setParameter('vtype', Product::TYPE_VARIABLE)
                    ->groupBy('p2.id');
                $rows = $connQb->getQuery()->getArrayResult();
                foreach ($rows as $row) {
                    $optionsCountById[(int)$row['id']] = (int)$row['cnt'];
                }
            }

            // Attach counts to lightweight resources
            foreach ($resources as $r) {
                if ($r->type === Product::TYPE_VARIABLE) {
                    $r->optionsCount = $optionsCountById[$r->id ?? 0] ?? 0;
                } else {
                    $r->optionsCount = null;
                }
            }

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
        $r->type = $entity->getType();
        $r->description = $entity->getDescription();
        $r->metaTitle = $entity->getMetaTitle();
        $r->metaDescription = $entity->getMetaDescription();
        $r->metaKeywords = $entity->getMetaKeywords();
        $r->h1 = $entity->getMetaH1();
        $r->optionsJson = $entity->getOptionsJson();
        // Include option assignments for admin edit (keeps Doctrine collection order)
        $r->optionAssignments = [];
        foreach ($entity->getOptionAssignments() as $a) {
            $r->optionAssignments[] = [
                'option' => '/api/options/' . $a->getOption()->getId(),
                'optionLabel' => $a->getOption()->getName(),
                'value' => '/api/option_values/' . $a->getValue()->getId(),
                'valueLabel' => $a->getValue()->getValue(),
                'height' => $a->getHeight(),
                'bulbsCount' => $a->getBulbsCount(),
                'sku' => $a->getSku(),
                'originalSku' => $a->getOriginalSku(),
                'price' => $a->getPrice(),
                'setPrice' => $a->getSetPrice() === null ? false : (bool) $a->getSetPrice(),
                'salePrice' => $a->getSalePrice(),
                'sortOrder' => $a->getSortOrder(),
                'quantity' => $a->getQuantity(),
                'lightingArea' => $a->getLightingArea(),
                'attributes' => $a->getAttributes(),
            ];
        }
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
        $r->type = $entity->getType();
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


