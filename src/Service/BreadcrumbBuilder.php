<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductToCategory;
use App\Repository\CategoryRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class BreadcrumbBuilder
{
    private const CACHE_VERSION = 'v2';
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheInterface $cache,
        private readonly CacheItemPoolInterface $cachePool,
    ) {}

    /**
     * @return array<int, array{label: string, url?: string}>
     */
    public function buildForCategory(Category $category): array
    {
        $cacheKey = sprintf('%s_breadcrumbs_category_%d', self::CACHE_VERSION, $category->getId());

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($category): array {
            $item->expiresAfter(3600);
            return $this->buildBreadcrumbsArray($category);
        });
    }

    /**
     * @return array<int, array{label: string, url?: string}>|null
     */
    public function buildForCategoryBySlug(string $slug): ?array
    {
        $cacheKey = sprintf('%s_breadcrumbs_slug_%s', self::CACHE_VERSION, $slug);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug): ?array {
            $item->expiresAfter(3600);

            $category = $this->categoryRepository->findOneVisibleBySlug($slug);
            if ($category === null) {
                return null;
            }

            return $this->buildBreadcrumbsArray($category);
        });
    }

    public function clearCacheForCategory(Category $category): void
    {
        $this->cachePool->deleteItem(sprintf('breadcrumbs_category_%d', $category->getId()));
        if ($category->getSlug() !== null) {
            $this->cachePool->deleteItem(sprintf('breadcrumbs_slug_%s', $category->getSlug()));
        }
    }

    /**
     * Строит breadcrumbs для товара с учётом основной категории и всех её предков
     * @return array<int, array{label: string, url?: string}>
     */
    public function buildForProduct(Product $product): array
    {
        $cacheKey = sprintf('%s_breadcrumbs_product_%d', self::CACHE_VERSION, $product->getId());

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($product): array {
            $item->expiresAfter(1800);

            $breadcrumbs = $this->getBaseBreadcrumbs();

            $primaryCategory = $this->selectPrimaryCategory($product);
            if ($primaryCategory instanceof Category) {
                $ancestors = $this->categoryRepository->getAncestorsFor($primaryCategory, 3);
                foreach ($ancestors as $ancestor) {
                    $breadcrumbs[] = [
                        'label' => $this->getCategoryLabel($ancestor),
                        'url' => $this->urlGenerator->generate('catalog_category_show', [
                            'slug' => (string) $ancestor->getSlug(),
                        ]),
                    ];
                }

                // Текущая категория товара
                $breadcrumbs[] = [
                    'label' => $this->getCategoryLabel($primaryCategory),
                    'url' => $this->urlGenerator->generate('catalog_category_show', [
                        'slug' => (string) $primaryCategory->getSlug(),
                    ]),
                ];
            }

            // Текущий товар (без ссылки)
            $breadcrumbs[] = [
                'label' => $product->getName() ?? $product->getSlug() ?? 'Товар',
            ];

            return $breadcrumbs;
        });
    }

    /**
     * @return array<int, array{label: string, url?: string}>
     */
    private function buildBreadcrumbsArray(Category $category): array
    {
        $breadcrumbs = $this->getBaseBreadcrumbs();

        $ancestors = $this->categoryRepository->getAncestorsFor($category, 3);
        foreach ($ancestors as $ancestor) {
            $breadcrumbs[] = [
                'label' => $this->getCategoryLabel($ancestor),
                'url' => $this->urlGenerator->generate('catalog_category_show', [
                    'slug' => (string) $ancestor->getSlug(),
                ]),
            ];
        }

        $breadcrumbs[] = [
            'label' => $this->getCategoryLabel($category),
        ];

        return $breadcrumbs;
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function getBaseBreadcrumbs(): array
    {
        return [
            [
                'label' => 'Главная',
                'url' => $this->urlGenerator->generate('app_home'),
            ],
        ];
    }

    private function getCategoryLabel(Category $category): string
    {
        return $category->getName()
            ?? $category->getSlug()
            ?? 'Категория';
    }

    private function selectPrimaryCategory(Product $product): ?Category
    {
        $relations = $product->getCategory();
        if ($relations->isEmpty()) {
            return null;
        }

        $visibleRelations = [];
        foreach ($relations as $relation) {
            if (!$relation instanceof ProductToCategory) {
                continue;
            }
            if ($relation->getVisibility() !== true) {
                continue;
            }
            if ($relation->getCategory() === null) {
                continue;
            }
            $visibleRelations[] = $relation;
        }

        if (count($visibleRelations) === 0) {
            return null;
        }

        // 1) приоритетная связь isParent=true
        foreach ($visibleRelations as $relation) {
            if ($relation->getIsParent() === true) {
                return $relation->getCategory();
            }
        }

        // 2) иначе минимальная позиция
        usort($visibleRelations, static function (ProductToCategory $a, ProductToCategory $b): int {
            return ($a->getPosition() ?? 0) <=> ($b->getPosition() ?? 0);
        });

        return $visibleRelations[0]->getCategory();
    }
}


