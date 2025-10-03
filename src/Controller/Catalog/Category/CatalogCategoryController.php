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
use Symfony\Component\HttpFoundation\Request;
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
    public function show(string $slug, Request $request): Response
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

        // Параметры пагинации/лимита (SSR для первой загрузки)
        $allowedLimits = (array)($this->getParameter('app.pagination.allowed_limits') ?? [10, 20, 30]);
        $defaultLimit = (int)($this->getParameter('app.pagination.default_limit') ?? 20);
        $currentLimit = (int) $request->query->getInt('limit', $defaultLimit);
        if (!in_array($currentLimit, array_map('intval', $allowedLimits), true)) {
            $currentLimit = $defaultLimit;
        }
        $page = max(1, (int)$request->query->getInt('page', 1));

        $priceMin = $request->query->get('price_min');
        $priceMax = $request->query->get('price_max');
        $priceMinInt = is_numeric((string)$priceMin) ? (int)$priceMin : null;
        $priceMaxInt = is_numeric((string)$priceMax) ? (int)$priceMax : null;
        $sort = $request->query->get('sort');

        // Определяем перечень кодов атрибутов/опций для типизированной фильтрации
        $categoryId = (int) $category->getId();
        $cfg = $this->facetConfigRepository->findEffectiveConfigForCategory($categoryId) ?? null;
        $attributeCodesFilter = [];
        $optionCodesFilter = [];
        if ($cfg) {
            foreach ($cfg->getAttributes() as $attr) {
                if (($attr['enabled'] ?? false) && !empty($attr['code'])) {
                    $attributeCodesFilter[] = (string)$attr['code'];
                }
            }
            foreach ($cfg->getOptions() as $opt) {
                if (($opt['enabled'] ?? false) && !empty($opt['code'])) {
                    $optionCodesFilter[] = (string)$opt['code'];
                }
            }
        }
        if (empty($attributeCodesFilter) && empty($optionCodesFilter)) {
            $rowFilter = $this->db->fetchAssociative('SELECT attributes_json, options_json FROM facet_dictionary WHERE category_id = :cid LIMIT 1', ['cid' => $categoryId]);
            if ($rowFilter) {
                $attrsF = json_decode($rowFilter['attributes_json'] ?? '[]', true) ?: [];
                $optsF = json_decode($rowFilter['options_json'] ?? '[]', true) ?: [];
                foreach (($attrsF['items'] ?? []) as $a) { if (!empty($a['code'])) $attributeCodesFilter[] = (string)$a['code']; }
                foreach ($optsF as $o) { if (!empty($o['code'])) $optionCodesFilter[] = (string)$o['code']; }
                $attributeCodesFilter = array_values(array_unique($attributeCodesFilter));
                $optionCodesFilter = array_values(array_unique($optionCodesFilter));
            }
        }

        // Используем типизированную фильтрацию, чтобы исключить ложные совпадения между атрибутами и опциями
        $result = $this->productRepository->paginateActiveByCategoryWithFacetsTyped(
            $category,
            $filters,
            $attributeCodesFilter,
            $optionCodesFilter,
            $page,
            $currentLimit,
            $priceMinInt,
            $priceMaxInt,
            is_string($sort) ? $sort : null
        );
        $items = $result['items'];
        $total = (int)$result['total'];

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
        $meta = [];
        // Хелпер: собрать JOIN/EXISTS по активным фильтрам, исключая текущий код
        $buildWhere = function(array $raw, ?string $excludeCode = null): array {
            $joins = '';
            $params = [];
            $types = [];
            $i = 0;
            $numericCodes = ['height', 'bulbs_count', 'lighting_area'];
            foreach ($raw as $c => $csv) {
                if ($excludeCode !== null && (string)$c === $excludeCode) continue;
                $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
                if (empty($values)) continue;

                $lower = strtolower((string)$c);
                if (in_array($lower, $numericCodes, true)) {
                    $intVals = array_values(array_filter(array_map(static fn($v) => is_numeric($v) ? (int)$v : null, $values), static fn($v) => $v !== null));
                    if (empty($intVals)) continue;
                    $i++;
                    $valsParam = 'f_vals_' . $i;
                    $col = $lower === 'bulbs_count' ? 'bulbs_count' : ($lower === 'lighting_area' ? 'lighting_area' : 'height');
                    $joins .= ' AND EXISTS (SELECT 1 FROM product_option_value_assignment pnum_f' . $i
                        . ' WHERE pnum_f' . $i . '.product_id = p.id AND pnum_f' . $i . '.' . $col . ' IN (:' . $valsParam . '))';
                    $params[$valsParam] = $intVals;
                    $types[$valsParam] = ArrayParameterType::INTEGER;
                    continue;
                }

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
            // Fill meta using config or DB
            $title = (string)$code;
            $sort = null;
            foreach (($config?->getAttributes() ?? []) as $a) {
                if (!empty($a['code']) && (string)$a['code'] === (string)$code) {
                    $title = isset($a['label']) && $a['label'] !== null && $a['label'] !== '' ? (string)$a['label'] : $title;
                    if (isset($a['order']) && $a['order'] !== null) $sort = (int)$a['order'];
                }
            }
            if ($title === (string)$code) {
                $n = $this->db->fetchOne('SELECT name FROM attribute WHERE code = :c LIMIT 1', ['c' => $code]);
                if ($n) $title = (string)$n;
            }
            $meta[$code] = [ 'title' => $title, 'sort' => $sort ];
        }
        foreach ($optionCodes as $code) {
            [$joinSql, $params, $types] = $buildWhere($rawFilters, $code);
            $lower = strtolower((string)$code);
            if (in_array($lower, ['height', 'bulbs_count', 'lighting_area'], true)) {
                $col = $lower === 'bulbs_count' ? 'bulbs_count' : ($lower === 'lighting_area' ? 'lighting_area' : 'height');
                $sql = 'SELECT CAST(pova.' . $col . ' AS CHAR) AS code, CAST(pova.' . $col . ' AS CHAR) AS label, COUNT(DISTINCT p.id) AS cnt
                     FROM product_option_value_assignment pova
                     INNER JOIN product p ON p.id = pova.product_id
                     INNER JOIN product_to_category pc ON pc.product_id = p.id'
                     . $joinSql .
                     ' WHERE p.status = 1 AND pc.category_id = :cid AND pova.' . $col . ' IS NOT NULL
                     GROUP BY pova.' . $col . '
                     ORDER BY cnt DESC';
                $params = array_merge($params, ['cid' => $categoryId]);
                $rows = $this->db->fetchAllAssociative($sql, $params, $types);
                $initialFacets[$code] = [
                    'type' => 'option',
                    'values' => array_map(static fn(array $r) => [
                        'code' => (string)$r['code'],
                        'label' => (string)$r['label'],
                        'count' => (int)$r['cnt'],
                    ], $rows)
                ];
            } else {
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
            // Fill meta using config or DB
            $title = (string)$code;
            $sort = null;
            foreach (($config?->getOptions() ?? []) as $o) {
                if (!empty($o['code']) && (string)$o['code'] === (string)$code) {
                    $title = isset($o['label']) && $o['label'] !== null && $o['label'] !== '' ? (string)$o['label'] : $title;
                    if (isset($o['order']) && $o['order'] !== null) $sort = (int)$o['order'];
                }
            }
            if ($title === (string)$code) {
                $n = $this->db->fetchOne('SELECT name FROM `option` WHERE code = :c LIMIT 1', ['c' => $code]);
                if ($n) $title = (string)$n;
            }
            $meta[$code] = [ 'title' => $title, 'sort' => $sort ];
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
            'total' => $total,
            'page' => $page,
            'limit' => $currentLimit,
            'allowedLimits' => $allowedLimits,
            'breadcrumbs' => $breadcrumbs,
            'initial_facets_json' => json_encode(['facets' => $initialFacets, 'meta' => $meta], JSON_UNESCAPED_UNICODE),
        ]);
    }

    #[Route('/{slug}/products', name: 'products', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function products(string $slug, Request $request): Response
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
        $allowedLimits = (array)($this->getParameter('app.pagination.allowed_limits') ?? [10, 20, 30]);
        $defaultLimit = (int)($this->getParameter('app.pagination.default_limit') ?? 20);
        $currentLimit = (int) $request->query->getInt('limit', $defaultLimit);
        if (!in_array($currentLimit, array_map('intval', $allowedLimits), true)) {
            $currentLimit = $defaultLimit;
        }
        $page = max(1, (int)$request->query->getInt('page', 1));

        $priceMin = $request->query->get('price_min');
        $priceMax = $request->query->get('price_max');
        $priceMinInt = is_numeric((string)$priceMin) ? (int)$priceMin : null;
        $priceMaxInt = is_numeric((string)$priceMax) ? (int)$priceMax : null;
        $sort = $request->query->get('sort');

        // Определяем перечень кодов атрибутов/опций для типизированной фильтрации
        $categoryId = (int) $category->getId();
        $cfg = $this->facetConfigRepository->findEffectiveConfigForCategory($categoryId) ?? null;
        $attributeCodesFilter = [];
        $optionCodesFilter = [];
        if ($cfg) {
            foreach ($cfg->getAttributes() as $attr) {
                if (($attr['enabled'] ?? false) && !empty($attr['code'])) {
                    $attributeCodesFilter[] = (string)$attr['code'];
                }
            }
            foreach ($cfg->getOptions() as $opt) {
                if (($opt['enabled'] ?? false) && !empty($opt['code'])) {
                    $optionCodesFilter[] = (string)$opt['code'];
                }
            }
        }
        if (empty($attributeCodesFilter) && empty($optionCodesFilter)) {
            $rowFilter = $this->db->fetchAssociative('SELECT attributes_json, options_json FROM facet_dictionary WHERE category_id = :cid LIMIT 1', ['cid' => $categoryId]);
            if ($rowFilter) {
                $attrsF = json_decode($rowFilter['attributes_json'] ?? '[]', true) ?: [];
                $optsF = json_decode($rowFilter['options_json'] ?? '[]', true) ?: [];
                foreach (($attrsF['items'] ?? []) as $a) { if (!empty($a['code'])) $attributeCodesFilter[] = (string)$a['code']; }
                foreach ($optsF as $o) { if (!empty($o['code'])) $optionCodesFilter[] = (string)$o['code']; }
                $attributeCodesFilter = array_values(array_unique($attributeCodesFilter));
                $optionCodesFilter = array_values(array_unique($optionCodesFilter));
            }
        }

        $result = $this->productRepository->paginateActiveByCategoryWithFacetsTyped(
            $category,
            $filters,
            $attributeCodesFilter,
            $optionCodesFilter,
            $page,
            $currentLimit,
            $priceMinInt,
            $priceMaxInt,
            is_string($sort) ? $sort : null
        );
        $items = $result['items'];
        $total = (int)$result['total'];

        return $this->render('catalog/category/_grid.html.twig', [
            'products' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $currentLimit,
            'slug' => $category->getSlug(),
        ]);
    }
}


