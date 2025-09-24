<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\ApiResource\ProductResource;
use App\Entity\Category;
use App\Entity\Option;
use App\Entity\OptionValue;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductOptionValueAssignment;
use App\Entity\ProductToCategory;
use App\Repository\CategoryRepository;
use App\Repository\OptionRepository as OptionRepo;
use App\Repository\OptionValueRepository as OptionValueRepo;
use App\Repository\ProductRepository;
use App\Repository\ProductToCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ProductFormController extends AbstractController
{
    #[Route('/api/admin/products/{id}/form', name: 'admin_api_product_form_edit', methods: ['GET'])]
    public function formEdit(
        int $id,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        ProductToCategoryRepository $ptcRepository,
        OptionRepo $optionRepository,
        OptionValueRepo $optionValueRepository,
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->json(['message' => 'Product not found'], 404);
        }

        $dto = $this->mapProductToResource($product);

        // Categories tree and selection
        $treePayload = $this->buildCategoriesTreePayload($categoryRepository);
        [$selectedCategoryIds, $mainCategoryId] = $this->loadProductCategorySelection($ptcRepository, $product);
        $categoriesPayload = [
            'treeVersion' => $treePayload['treeVersion'],
            'tree' => $treePayload['tree'],
            'selectedCategoryIds' => array_values($selectedCategoryIds),
            'mainCategoryId' => $mainCategoryId,
        ];

        // Options dictionary: provide compact list and values map for options used by the product
        $optionsPayload = $this->buildOptionsPayloadForProduct($product, $optionRepository, $optionValueRepository);

        // Photos
        $photos = [];
        foreach ($product->getImage() as $img) {
            if ($img instanceof ProductImage) {
                $photos[] = [
                    'id' => $img->getId(),
                    'imageUrl' => $img->getImageUrl(),
                    'sortOrder' => $img->getSortOrder(),
                ];
            }
        }

        // Dict versions (simple counters as versions)
        $dictVersions = [
            'categories' => $treePayload['treeVersion'],
            'options' => $optionsPayload['version'],
        ];

        $flags = [
            'isVariableWithoutVariations' => ($dto->type === Product::TYPE_VARIABLE) && empty($dto->optionAssignments ?? []),
        ];

        return $this->json([
            'product' => $dto,
            'categories' => $categoriesPayload,
            'options' => [
                'version' => $optionsPayload['version'],
                'list' => $optionsPayload['list'],
                'valuesByOption' => $optionsPayload['valuesByOption'],
            ],
            'photos' => $photos,
            'dictVersions' => $dictVersions,
            'flags' => $flags,
        ]);
    }

    #[Route('/api/admin/products/form', name: 'admin_api_product_form_new', methods: ['GET'])]
    public function formNew(
        CategoryRepository $categoryRepository,
        OptionRepo $optionRepository,
        OptionValueRepo $optionValueRepository,
    ): JsonResponse {
        // Default empty product payload (plain array to avoid IRI generation for a non-persisted ApiResource)
        $product = [
            'id' => null,
            'type' => Product::TYPE_SIMPLE,
            'name' => '',
            'slug' => '',
            'price' => null,
            'salePrice' => null,
            'status' => true,
            'quantity' => null,
            'description' => '',
            'metaTitle' => '',
            'metaDescription' => '',
            'h1' => '',
            'sortOrder' => 0,
            'optionAssignments' => [],
            'image' => [],
        ];

        // Categories tree
        $treePayload = $this->buildCategoriesTreePayload($categoryRepository);
        $categoriesPayload = [
            'treeVersion' => $treePayload['treeVersion'],
            'tree' => $treePayload['tree'],
            'selectedCategoryIds' => [],
            'mainCategoryId' => null,
        ];

        // Options dictionary (provide compact list only; values lazy on demand)
        $optionsPayload = $this->buildOptionsPayloadCompact($optionRepository, $optionValueRepository, includeValues: false);

        $dictVersions = [
            'categories' => $treePayload['treeVersion'],
            'options' => $optionsPayload['version'],
        ];

        return $this->json([
            'product' => $product,
            'categories' => $categoriesPayload,
            'options' => [
                'version' => $optionsPayload['version'],
                'list' => $optionsPayload['list'],
                'valuesByOption' => new \stdClass(),
            ],
            'photos' => [],
            'dictVersions' => $dictVersions,
            'flags' => [
                'isVariableWithoutVariations' => false,
            ],
        ]);
    }

    private function mapProductToResource(Product $product): ProductResource
    {
        $dto = new ProductResource();
        $dto->id = $product->getId();
        $dto->code = $product->getCode() ? (string) $product->getCode() : null;
        $dto->name = $product->getName();
        $dto->slug = $product->getSlug();
        $dto->price = $product->getPrice();
        $dto->salePrice = $product->getSalePrice();
        $dto->effectivePrice = $product->getEffectivePrice();
        $dto->status = $product->getStatus();
        $dto->quantity = $product->getQuantity();
        $dto->sortOrder = $product->getSortOrder();
        $dto->description = $product->getDescription();
        $dto->metaTitle = $product->getMetaTitle();
        $dto->metaDescription = $product->getMetaDescription();
        $dto->metaKeywords = $product->getMetaKeywords();
        $dto->h1 = $product->getMetaH1();
        $dto->type = $product->getType();
        $dto->manufacturerId = $product->getManufacturerRef()?->getId();
        $dto->manufacturerName = $product->getManufacturerRef()?->getName();

        // Option assignments mapped to IRIs
        $assignments = [];
        foreach ($product->getOptionAssignments() as $a) {
            if (!$a instanceof ProductOptionValueAssignment) {
                continue;
            }
            $assignments[] = [
                'option' => '/api/options/' . $a->getOption()->getId(),
                'value' => '/api/option_values/' . $a->getValue()->getId(),
                'height' => $a->getHeight(),
                'bulbsCount' => $a->getBulbsCount(),
                'sku' => $a->getSku(),
                'originalSku' => $a->getOriginalSku(),
                'price' => $a->getPrice(),
                'setPrice' => $a->getSetPrice(),
                'salePrice' => $a->getSalePrice(),
                'lightingArea' => $a->getLightingArea(),
                'sortOrder' => $a->getSortOrder(),
                'quantity' => $a->getQuantity(),
                'attributes' => $a->getAttributes(),
            ];
        }
        $dto->optionAssignments = $assignments;

        // Images (normalized below in photos)
        $dto->image = [];

        return $dto;
    }

    /**
     * Build categories tree and version (md5 signature of ids and parent relations).
     * @return array{treeVersion: string, tree: array<int, array<string, mixed>>}
     */
    private function buildCategoriesTreePayload(CategoryRepository $categoryRepository): array
    {
        $all = $categoryRepository->createQueryBuilder('c')
            ->select('c.id, c.name, c.parentCategoryId')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $byId = [];
        foreach ($all as $row) {
            $byId[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'label' => (string) ($row['name'] ?? ''),
                'parentId' => $row['parentCategoryId'] !== null ? (int) $row['parentCategoryId'] : null,
                'children' => [],
            ];
        }
        $roots = [];
        foreach ($byId as $cid => $node) {
            $pid = $node['parentId'];
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$byId[$cid];
            } else {
                $roots[] = &$byId[$cid];
            }
        }
        // sort children by label
        $sortRec = function (&$nodes) use (&$sortRec) {
            usort($nodes, static fn($a, $b) => strcmp((string) $a['label'], (string) $b['label']));
            foreach ($nodes as &$n) {
                if (!empty($n['children'])) $sortRec($n['children']);
            }
        };
        $sortRec($roots);

        // version signature
        $sigBase = implode(';', array_map(static function ($r) { return (string) ($r['id'] . ':' . ($r['parentCategoryId'] ?? 'null')); }, $all));
        $treeVersion = 'v' . substr(sha1($sigBase), 0, 12);

        return [
            'treeVersion' => $treeVersion,
            'tree' => $roots,
        ];
    }

    /**
     * @return array{0: int[], 1: int|null}
     */
    private function loadProductCategorySelection(ProductToCategoryRepository $ptcRepository, Product $product): array
    {
        $rels = $ptcRepository->createQueryBuilder('pc')
            ->leftJoin('pc.category', 'c')
            ->addSelect('c')
            ->where('pc.product = :p')
            ->setParameter('p', $product)
            ->getQuery()
            ->getResult();
        $selected = [];
        $main = null;
        foreach ($rels as $r) {
            if ($r instanceof ProductToCategory) {
                $cid = $r->getCategory()?->getId();
                if ($cid) $selected[] = (int) $cid;
                if ($r->getIsParent()) $main = $cid ? (int) $cid : null;
            }
        }
        return [array_values(array_unique($selected)), $main];
    }

    /**
     * Build options payload for the options actually used by the product.
     */
    private function buildOptionsPayloadForProduct(Product $product, OptionRepo $optionRepository, OptionValueRepo $optionValueRepository): array
    {
        $usedOptionIds = [];
        foreach ($product->getOptionAssignments() as $a) {
            if ($a instanceof ProductOptionValueAssignment) {
                $usedOptionIds[] = (int) $a->getOption()->getId();
            }
        }
        $usedOptionIds = array_values(array_unique($usedOptionIds));
        $list = [];
        $valuesByOption = [];
        if ($usedOptionIds) {
            $opts = $optionRepository->createQueryBuilder('o')
                ->where('o.id IN (:ids)')
                ->setParameter('ids', $usedOptionIds)
                ->orderBy('o.sortOrder', 'ASC')
                ->addOrderBy('o.name', 'ASC')
                ->getQuery()->getArrayResult();
            foreach ($opts as $o) {
                $list[] = [ 'id' => (int) $o['id'], 'name' => (string) $o['name'] ];
            }
            $vals = $optionValueRepository->createQueryBuilder('v')
                ->select('v.id AS id, v.value AS value, o.id AS oid')
                ->leftJoin('v.optionType', 'o')
                ->where('o.id IN (:ids)')
                ->setParameter('ids', $usedOptionIds)
                ->orderBy('o.id', 'ASC')
                ->addOrderBy('v.sortOrder', 'ASC')
                ->getQuery()->getArrayResult();
            foreach ($vals as $row) {
                $oid = (int) ($row['oid'] ?? 0);
                if (!$oid) continue;
                $valuesByOption[(string)$oid] ??= [];
                $valuesByOption[(string)$oid][] = [ 'id' => (int) $row['id'], 'value' => (string) $row['value'] ];
            }
        }

        // Version signature for options/values: based on counts
        $counts = [
            'o' => (int) $optionRepository->createQueryBuilder('o')->select('COUNT(o.id)')->getQuery()->getSingleScalarResult(),
            'v' => (int) $optionValueRepository->createQueryBuilder('v')->select('COUNT(v.id)')->getQuery()->getSingleScalarResult(),
        ];
        $version = 'v' . $counts['o'] . '_' . $counts['v'];

        return [
            'version' => $version,
            'list' => $list,
            'valuesByOption' => $valuesByOption,
        ];
    }

    /**
     * Compact options payload (without values) for new form.
     */
    private function buildOptionsPayloadCompact(OptionRepo $optionRepository, OptionValueRepo $optionValueRepository, bool $includeValues = false): array
    {
        $opts = $optionRepository->createQueryBuilder('o')
            ->orderBy('o.sortOrder', 'ASC')
            ->addOrderBy('o.name', 'ASC')
            ->getQuery()->getArrayResult();
        $list = array_map(static fn(array $o) => [ 'id' => (int) $o['id'], 'name' => (string) $o['name'] ], $opts);
        $valuesByOption = [];
        if ($includeValues) {
            $vals = $optionValueRepository->createQueryBuilder('v')
                ->select('v.id AS id, v.value AS value, o.id AS oid')
                ->leftJoin('v.optionType', 'o')
                ->orderBy('o.id', 'ASC')
                ->addOrderBy('v.sortOrder', 'ASC')
                ->getQuery()->getArrayResult();
            foreach ($vals as $row) {
                $oid = (int) ($row['oid'] ?? 0);
                if (!$oid) continue;
                $valuesByOption[(string)$oid] ??= [];
                $valuesByOption[(string)$oid][] = [ 'id' => (int) $row['id'], 'value' => (string) $row['value'] ];
            }
        }
        $counts = [
            'o' => (int) $optionRepository->createQueryBuilder('o')->select('COUNT(o.id)')->getQuery()->getSingleScalarResult(),
            'v' => (int) $optionValueRepository->createQueryBuilder('v')->select('COUNT(v.id)')->getQuery()->getSingleScalarResult(),
        ];
        $version = 'v' . $counts['o'] . '_' . $counts['v'];

        return [
            'version' => $version,
            'list' => $list,
            'valuesByOption' => $valuesByOption,
        ];
    }
}


