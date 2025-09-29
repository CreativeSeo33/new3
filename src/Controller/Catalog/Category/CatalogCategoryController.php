<?php
declare(strict_types=1);

namespace App\Controller\Catalog\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\BreadcrumbBuilder;
use App\Repository\FacetConfigRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/category', name: 'catalog_category_')]
final class CatalogCategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly BreadcrumbBuilder $breadcrumbBuilder,
        private readonly Connection $db,
        private readonly FacetConfigRepository $facetConfigRepository,
    ) {}
    

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function show(string $slug, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug, 'visibility' => true]);

        if ($category === null) {
            throw $this->createNotFoundException('Категория не найдена');
        }

        // Применяем фильтры из URL (f[code]=csv)
        $rawFilters = $request->query->all('f');
        $filters = [];
        foreach ($rawFilters as $code => $csv) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
            if (!empty($values)) $filters[(string)$code] = $values;
        }

        $items = empty($filters)
            ? $this->productRepository->findActiveByCategory($category, 20, 0)
            : $this->productRepository->findActiveByCategoryWithFacets($category, $filters, 20, 0);

        // Build initial facets on backend (no client API call)
        $categoryId = (int) $category->getId();
        $config = $this->facetConfigRepository->findEffectiveConfigForCategory($categoryId) ?? null;
        $attributeCodes = [];
        $optionCodes = [];
        if ($config) {
            foreach ($config->getAttributes() as $attr) {
                if (($attr['enabled'] ?? false) && !empty($attr['code'])) {
                    $attributeCodes[] = (string)$attr['code'];
                }
            }
            foreach ($config->getOptions() as $opt) {
                if (($opt['enabled'] ?? false) && !empty($opt['code'])) {
                    $optionCodes[] = (string)$opt['code'];
                }
            }
        }
        if (empty($attributeCodes) && empty($optionCodes)) {
            $row = $this->db->fetchAssociative('SELECT attributes_json, options_json FROM facet_dictionary WHERE category_id = :cid LIMIT 1', ['cid' => $categoryId]);
            if ($row) {
                $attrs = json_decode($row['attributes_json'] ?? '[]', true) ?: [];
                $opts = json_decode($row['options_json'] ?? '[]', true) ?: [];
                foreach (($attrs['items'] ?? []) as $a) { if (!empty($a['code'])) $attributeCodes[] = (string)$a['code']; }
                foreach ($opts as $o) { if (!empty($o['code'])) $optionCodes[] = (string)$o['code']; }
                $attributeCodes = array_values(array_unique($attributeCodes));
                $optionCodes = array_values(array_unique($optionCodes));
            }
        }

        $initialFacets = [];
        // Хелпер: собрать JOIN/EXISTS по активным фильтрам, исключая текущий код
        $buildWhere = function(array $raw, ?string $excludeCode = null): array {
            $joins = '';
            $params = [];
            $types = [];
            $i = 0;
            foreach ($raw as $c => $csv) {
                if ($excludeCode !== null && (string)$c === $excludeCode) continue;
                $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
                if (empty($values)) continue;
                $i++;
                $codeParam = 'f_code_' . $i;
                $valsParam = 'f_vals_' . $i;
                $joins .= ' AND ('
                    . 'EXISTS (SELECT 1 FROM product_attribute_assignment paa_f' . $i . ' INNER JOIN attribute a_f' . $i . ' ON a_f' . $i . '.id = paa_f' . $i . '.attribute_id WHERE paa_f' . $i . '.product_id = p.id AND a_f' . $i . '.code = :' . $codeParam . ' AND paa_f' . $i . '.string_value IN (:' . $valsParam . '))'
                    . ' OR EXISTS (SELECT 1 FROM product_option_value_assignment pova_f' . $i . ' INNER JOIN `option` o_f' . $i . ' ON o_f' . $i . '.id = pova_f' . $i . '.option_id INNER JOIN option_value ov_f' . $i . ' ON ov_f' . $i . '.id = pova_f' . $i . '.value_id WHERE pova_f' . $i . '.product_id = p.id AND o_f' . $i . '.code = :' . $codeParam . ' AND ov_f' . $i . '.value IN (:' . $valsParam . '))'
                    . ')';
                $params[$codeParam] = (string)$c;
                $params[$valsParam] = $values;
                $types[$valsParam] = ArrayParameterType::STRING;
            }
            return [$joins, $params, $types];
        };

        foreach ($attributeCodes as $code) {
            [$joinSql, $params, $types] = $buildWhere($rawFilters, $code);
            $sql = 'SELECT paa.string_value AS code, paa.string_value AS label, COUNT(DISTINCT p.id) AS cnt
                 FROM product_attribute_assignment paa
                 INNER JOIN attribute a ON a.id = paa.attribute_id
                 INNER JOIN product p ON p.id = paa.product_id
                 INNER JOIN product_to_category pc ON pc.product_id = p.id'
                 . $joinSql .
                 ' WHERE p.status = 1 AND pc.category_id = :cid AND a.code = :code AND paa.string_value IS NOT NULL
                 GROUP BY paa.string_value
                 ORDER BY cnt DESC';
            $params = array_merge($params, ['cid' => $categoryId, 'code' => $code]);
            $rows = $this->db->fetchAllAssociative($sql, $params, $types);
            $initialFacets[$code] = [
                'type' => 'attribute',
                'values' => array_map(static fn(array $r) => [
                    'code' => (string)$r['code'],
                    'label' => (string)$r['label'],
                    'count' => (int)$r['cnt'],
                ], $rows)
            ];
        }
        foreach ($optionCodes as $code) {
            [$joinSql, $params, $types] = $buildWhere($rawFilters, $code);
            $sql = 'SELECT ov.code AS code, ov.value AS label, COUNT(DISTINCT p.id) AS cnt
                 FROM product_option_value_assignment pova
                 INNER JOIN `option` o ON o.id = pova.option_id
                 INNER JOIN option_value ov ON ov.id = pova.value_id
                 INNER JOIN product p ON p.id = pova.product_id
                 INNER JOIN product_to_category pc ON pc.product_id = p.id'
                 . $joinSql .
                 ' WHERE p.status = 1 AND pc.category_id = :cid AND o.code = :code
                 GROUP BY ov.code, ov.value
                 ORDER BY cnt DESC';
            $params = array_merge($params, ['cid' => $categoryId, 'code' => $code]);
            $rows = $this->db->fetchAllAssociative($sql, $params, $types);
            $initialFacets[$code] = [
                'type' => 'option',
                'values' => array_map(static fn(array $r) => [
                    'code' => (string)$r['code'],
                    'label' => (string)$r['label'],
                    'count' => (int)$r['cnt'],
                ], $rows)
            ];
        }
        $priceRow = $this->db->fetchAssociative(
            'SELECT MIN(p.effective_price) AS min_price, MAX(p.effective_price) AS max_price FROM product p INNER JOIN product_to_category pc ON pc.product_id = p.id WHERE p.status = 1 AND pc.category_id = :cid',
            ['cid' => $categoryId]
        ) ?: ['min_price' => null, 'max_price' => null];
        $initialFacets['price'] = [
            'type' => 'range',
            'min' => $priceRow['min_price'] ?? null,
            'max' => $priceRow['max_price'] ?? null,
        ];

        $breadcrumbs = $this->breadcrumbBuilder->buildForCategory($category);

        return $this->render('catalog/category/show.html.twig', [
            'category' => $category,
            'products' => $items,
            'breadcrumbs' => $breadcrumbs,
            'initial_facets_json' => json_encode(['facets' => $initialFacets], JSON_UNESCAPED_UNICODE),
        ]);
    }

    #[Route('/{slug}/products', name: 'products', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function products(string $slug, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug, 'visibility' => true]);
        if ($category === null) {
            throw $this->createNotFoundException('Категория не найдена');
        }

        // Применяем фильтры вида f[code]=csv
        $raw = $request->query->all('f');
        $filters = [];
        foreach ($raw as $code => $csv) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
            if (!empty($values)) $filters[(string)$code] = $values;
        }
        $items = empty($filters)
            ? $this->productRepository->findActiveByCategory($category, 20, 0)
            : $this->productRepository->findActiveByCategoryWithFacets($category, $filters, 20, 0);

        return $this->render('catalog/category/_grid.html.twig', [
            'products' => $items,
        ]);
    }
}


