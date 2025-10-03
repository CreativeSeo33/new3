<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Repository\ProductRepository;
use App\Service\Search\ProductSearch;
use App\Entity\FacetConfig;
use App\Repository\FacetConfigRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductSearch $productSearch,
        private readonly FacetConfigRepository $facetConfigRepository,
        private readonly Connection $db,
    ) {}

    #[Route(path: '/search/', name: 'catalog_search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $text = trim((string)$request->query->get('text', ''));

        // Параметры пагинации
        $allowedLimits = (array)($this->getParameter('app.pagination.allowed_limits') ?? [10, 20, 30]);
        $defaultLimit = (int)($this->getParameter('app.pagination.default_limit') ?? 20);
        $currentLimit = (int)$request->query->getInt('limit', $defaultLimit);
        if (!in_array($currentLimit, array_map('intval', $allowedLimits), true)) {
            $currentLimit = $defaultLimit;
        }
        $page = max(1, (int)$request->query->getInt('page', 1));

        $products = [];
        $total = 0;
        $initialFacets = [];
        $meta = [];
        $valuesLimit = (int)($this->getParameter('app.facets.values_limit_default') ?? 0);
        if ($text !== '') {
            // Получаем релевантные id из поискового индекса
            $found = $this->productSearch->search($text, 5000, 0);
            $ids = array_map('intval', $found['ids'] ?? []);
            $total = (int)($found['total'] ?? 0);

            if (!empty($ids)) {
                // Пагинация по порядку релевантности
                $offset = ($page - 1) * $currentLimit;
                $pagedIds = array_slice($ids, $offset, $currentLimit);
                if (!empty($pagedIds)) {
                    $sort = (string)($request->query->get('sort') ?? 'relevance');
                    $qb = $this->productRepository->createQueryBuilder('p')
                        ->leftJoin('p.image', 'img')->addSelect('img')
                        ->andWhere('p.id IN (:ids)')
                        ->andWhere('p.status = true')
                        ->setParameter('ids', $pagedIds);
                    // Применяем сортировку для search (по умолчанию сохраняем порядок релевантности)
                    switch (strtolower($sort)) {
                        case 'popular':
                            $qb->orderBy('p.sortOrder', 'ASC');
                            break;
                        case 'price_asc':
                            $qb->orderBy('p.effectivePrice', 'ASC');
                            break;
                        case 'price_desc':
                            $qb->orderBy('p.effectivePrice', 'DESC');
                            break;
                        case 'date_asc':
                            $qb->orderBy('p.timestamps.createdAt', 'ASC');
                            break;
                        case 'date_desc':
                            $qb->orderBy('p.timestamps.createdAt', 'DESC');
                            break;
                        case 'name_asc':
                            $qb->orderBy('p.name', 'ASC');
                            break;
                        case 'name_desc':
                            $qb->orderBy('p.name', 'DESC');
                            break;
                        case 'relevance':
                        default:
                            // Сохраняем порядок $pagedIds; Doctrine не гарантирует порядок IN(...)
                            $entities = $qb->getQuery()->getResult();
                            $byId = [];
                            foreach ($entities as $e) { $byId[$e->getId()] = $e; }
                            foreach ($pagedIds as $id) { if (isset($byId[$id])) { $products[] = $byId[$id]; } }
                            $entities = null; // уже собрали $products
                            break;
                    }
                    if (empty($products)) {
                        // Для всех сортировок кроме relevance просто возьмём результат ORM
                        $entities = $qb->getQuery()->getResult();
                        $products = is_array($entities) ? $entities : [];
                    }
                }

                // === Build initial facets/meta for SSR (search-mode) ===
                $rawFilters = $request->query->all('f');
                $attributeCodes = [];
                $optionCodes = [];
                $config = $this->facetConfigRepository->findOneBy(['scope' => FacetConfig::SCOPE_GLOBAL]);
                if ($config) {
                    foreach ($config->getAttributes() as $attr) {
                        if (($attr['enabled'] ?? false) === true && !empty($attr['code'])) {
                            $attributeCodes[] = (string)$attr['code'];
                        }
                    }
                    foreach ($config->getOptions() as $opt) {
                        if (($opt['enabled'] ?? false) === true && !empty($opt['code'])) {
                            $optionCodes[] = (string)$opt['code'];
                        }
                    }
                }
                if (empty($attributeCodes) && empty($optionCodes)) {
                    $row = $this->db->fetchAssociative('SELECT attributes_json, options_json FROM facet_dictionary WHERE category_id IS NULL LIMIT 1');
                    if ($row) {
                        $attrs = json_decode($row['attributes_json'] ?? '[]', true) ?: [];
                        $opts = json_decode($row['options_json'] ?? '[]', true) ?: [];
                        foreach (($attrs['items'] ?? []) as $a) {
                            if (empty($a['code'])) continue;
                            $code = (string)$a['code'];
                            $attributeCodes[] = $code;
                            $meta[$code] = [
                                'title' => (string)($a['name'] ?? $code),
                                'sort' => isset($a['sort']) ? (int)$a['sort'] : null,
                            ];
                        }
                        foreach ($opts as $o) {
                            if (empty($o['code'])) continue;
                            $code = (string)$o['code'];
                            $optionCodes[] = $code;
                            $meta[$code] = [
                                'title' => (string)($o['name'] ?? $code),
                                'sort' => isset($o['sort']) ? (int)$o['sort'] : null,
                            ];
                        }
                        $attributeCodes = array_values(array_unique($attributeCodes));
                        $optionCodes = array_values(array_unique($optionCodes));
                    }
                }
                if (empty($attributeCodes) && empty($optionCodes)) {
                    // auto-discover codes from ids subset
                    $attrRows = $this->db->fetchFirstColumn(
                        'SELECT DISTINCT a.code
                         FROM product_attribute_assignment paa
                         INNER JOIN attribute a ON a.id = paa.attribute_id
                         INNER JOIN product p ON p.id = paa.product_id
                         WHERE p.status = 1 AND paa.string_value IS NOT NULL AND p.id IN (:ids)',
                        ['ids' => $ids],
                        ['ids' => ArrayParameterType::INTEGER]
                    );
                    $optRows = $this->db->fetchFirstColumn(
                        'SELECT DISTINCT o.code
                         FROM product_option_value_assignment pova
                         INNER JOIN `option` o ON o.id = pova.option_id
                         INNER JOIN product p ON p.id = pova.product_id
                         WHERE p.status = 1 AND p.id IN (:ids)',
                        ['ids' => $ids],
                        ['ids' => ArrayParameterType::INTEGER]
                    );
                    $attributeCodes = array_map('strval', $attrRows ?: []);
                    $optionCodes = array_map('strval', $optRows ?: []);
                }

                // helper: build filter joins excluding current code
                $buildWhere = function(array $raw, ?string $excludeCode = null) use ($request) {
                    $joins = '';
                    $params = [];
                    $types = [];
                    $i = 0;
                    $numericCodes = ['height', 'bulbs_count', 'lighting_area'];
                    // Фильтр по цене: применяем к запросам агрегаций на ids
                    $priceMinRaw = $request->query->get('price_min');
                    $priceMaxRaw = $request->query->get('price_max');
                    if ($priceMinRaw !== null && is_numeric((string)$priceMinRaw)) {
                        $joins .= ' AND p.effective_price >= :f_price_min';
                        $params['f_price_min'] = (int)$priceMinRaw;
                        $types['f_price_min'] = \PDO::PARAM_INT;
                    }
                    if ($priceMaxRaw !== null && is_numeric((string)$priceMaxRaw)) {
                        $joins .= ' AND p.effective_price <= :f_price_max';
                        $params['f_price_max'] = (int)$priceMaxRaw;
                        $types['f_price_max'] = \PDO::PARAM_INT;
                    }
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
                        if ($lower === 'category') {
                            $i++;
                            $valsParam = 'f_vals_' . $i;
                            $joins .= ' AND EXISTS (SELECT 1 FROM product_to_category pc_f' . $i
                                . ' INNER JOIN category c_f' . $i . ' ON c_f' . $i . '.id = pc_f' . $i . '.category_id'
                                . ' WHERE pc_f' . $i . '.product_id = p.id AND pc_f' . $i . '.is_parent = 1 AND c_f' . $i . '.name IN (:' . $valsParam . '))';
                            $params[$valsParam] = $values;
                            $types[$valsParam] = ArrayParameterType::STRING;
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

                // attributes facets
                foreach ($attributeCodes as $code) {
                    [$joinSql, $params, $types] = $buildWhere($rawFilters, $code);
                    $sql = 'SELECT paa.string_value AS code, paa.string_value AS label, COUNT(DISTINCT p.id) AS cnt'
                        . ' FROM product_attribute_assignment paa'
                        . ' INNER JOIN attribute a ON a.id = paa.attribute_id'
                        . ' INNER JOIN product p ON p.id = paa.product_id'
                        . $joinSql
                        . ' WHERE p.status = 1 AND a.code = :code AND paa.string_value IS NOT NULL AND p.id IN (:ids)'
                        . ' GROUP BY paa.string_value ORDER BY cnt DESC';
                    $params = array_merge($params, ['code' => $code, 'ids' => $ids]);
                    $types['ids'] = ArrayParameterType::INTEGER;
                    $rows = $this->db->fetchAllAssociative($sql, $params, $types);
                    $values = array_map(static fn(array $r) => [
                        'code' => (string)$r['code'],
                        'label' => (string)$r['label'],
                        'count' => (int)$r['cnt'],
                    ], $rows);
                    if ($valuesLimit > 0 && count($values) > $valuesLimit) {
                        $values = array_slice($values, 0, $valuesLimit);
                    }
                    $initialFacets[$code] = ['type' => 'attribute', 'values' => $values];

                    // meta for attribute
                    $title = (string)$code; $sort = null;
                    if ($config) {
                        foreach ($config->getAttributes() as $a) {
                            if (!empty($a['code']) && (string)$a['code'] === (string)$code) {
                                $title = isset($a['label']) && $a['label'] !== null && $a['label'] !== '' ? (string)$a['label'] : $title;
                                if (isset($a['order']) && $a['order'] !== null) $sort = (int)$a['order'];
                            }
                        }
                    }
                    if ($title === (string)$code) {
                        $n = $this->db->fetchOne('SELECT name FROM attribute WHERE code = :c LIMIT 1', ['c' => $code]);
                        if ($n) $title = (string)$n;
                    }
                    $meta[$code] = $meta[$code] ?? [ 'title' => $title, 'sort' => $sort ];
                }

                // options facets
                foreach ($optionCodes as $code) {
                    [$joinSql, $params, $types] = $buildWhere($rawFilters, $code);
                    $lower = strtolower((string)$code);
                    if (in_array($lower, ['height', 'bulbs_count', 'lighting_area'], true)) {
                        $col = $lower === 'bulbs_count' ? 'bulbs_count' : ($lower === 'lighting_area' ? 'lighting_area' : 'height');
                        $sql = 'SELECT CAST(pova.' . $col . ' AS CHAR) AS code, CAST(pova.' . $col . ' AS CHAR) AS label, COUNT(DISTINCT p.id) AS cnt'
                            . ' FROM product_option_value_assignment pova'
                            . ' INNER JOIN product p ON p.id = pova.product_id'
                            . $joinSql
                            . ' WHERE p.status = 1 AND pova.' . $col . ' IS NOT NULL AND p.id IN (:ids)'
                            . ' GROUP BY pova.' . $col . ' ORDER BY cnt DESC';
                        $params = array_merge($params, ['ids' => $ids]);
                        $types['ids'] = ArrayParameterType::INTEGER;
                        $rows = $this->db->fetchAllAssociative($sql, $params, $types);
                        $values = array_map(static fn(array $r) => [
                            'code' => (string)$r['code'],
                            'label' => (string)$r['label'],
                            'count' => (int)$r['cnt'],
                        ], $rows);
                        if ($valuesLimit > 0 && count($values) > $valuesLimit) {
                            $values = array_slice($values, 0, $valuesLimit);
                        }
                        $initialFacets[$code] = ['type' => 'option', 'values' => $values];
                    } else {
                        $sql = 'SELECT ov.code AS code, ov.value AS label, COUNT(DISTINCT p.id) AS cnt'
                            . ' FROM product_option_value_assignment pova'
                            . ' INNER JOIN `option` o ON o.id = pova.option_id'
                            . ' INNER JOIN option_value ov ON ov.id = pova.value_id'
                            . ' INNER JOIN product p ON p.id = pova.product_id'
                            . $joinSql
                            . ' WHERE p.status = 1 AND o.code = :code AND p.id IN (:ids)'
                            . ' GROUP BY ov.code, ov.value ORDER BY cnt DESC';
                        $params = array_merge($params, ['code' => $code, 'ids' => $ids]);
                        $types['ids'] = ArrayParameterType::INTEGER;
                        $rows = $this->db->fetchAllAssociative($sql, $params, $types);
                        $values = array_map(static fn(array $r) => [
                            'code' => (string)$r['code'],
                            'label' => (string)$r['label'],
                            'count' => (int)$r['cnt'],
                        ], $rows);
                        if ($valuesLimit > 0 && count($values) > $valuesLimit) {
                            $values = array_slice($values, 0, $valuesLimit);
                        }
                        $initialFacets[$code] = ['type' => 'option', 'values' => $values];
                    }

                    // meta for option
                    $title = (string)$code; $sort = null;
                    if ($config) {
                        foreach ($config->getOptions() as $o) {
                            if (!empty($o['code']) && (string)$o['code'] === (string)$code) {
                                $title = isset($o['label']) && $o['label'] !== null && $o['label'] !== '' ? (string)$o['label'] : $title;
                                if (isset($o['order']) && $o['order'] !== null) $sort = (int)$o['order'];
                            }
                        }
                    }
                    if ($title === (string)$code) {
                        $n = $this->db->fetchOne('SELECT name FROM `option` WHERE code = :c LIMIT 1', ['c' => $code]);
                        if ($n) $title = (string)$n;
                    }
                    $meta[$code] = $meta[$code] ?? [ 'title' => $title, 'sort' => $sort ];
                }

                // category facet (по найденному подмножеству ids, только is_parent = 1)
                [$joinSql, $params, $types] = $buildWhere($rawFilters, 'category');
                $sql = 'SELECT c.id AS code, c.name AS label, COUNT(DISTINCT p.id) AS cnt'
                    . ' FROM product_to_category pc'
                    . ' INNER JOIN category c ON c.id = pc.category_id'
                    . ' INNER JOIN product p ON p.id = pc.product_id'
                    . $joinSql
                    . ' WHERE p.status = 1 AND pc.is_parent = 1 AND p.id IN (:ids)'
                    . ' GROUP BY c.id, c.name ORDER BY cnt DESC';
                $params = array_merge($params, ['ids' => $ids]);
                $types['ids'] = ArrayParameterType::INTEGER;
                $rows = $this->db->fetchAllAssociative($sql, $params, $types);
                $catValues = array_map(static fn(array $r) => [
                    'code' => (string)$r['code'],
                    'label' => (string)$r['label'],
                    'count' => (int)$r['cnt'],
                ], $rows);
                if ($valuesLimit > 0 && count($catValues) > $valuesLimit) {
                    $catValues = array_slice($catValues, 0, $valuesLimit);
                }
                $initialFacets['category'] = ['type' => 'category', 'values' => $catValues];
                $meta['category'] = $meta['category'] ?? ['title' => 'Категории', 'sort' => null];

                // Price range for ids (include variants like in FacetsController)
                $priceRow = $this->db->fetchAssociative(
                    'SELECT MIN(t.price_val) AS min_price, MAX(t.price_val) AS max_price
                     FROM (
                       SELECT p.effective_price AS price_val
                       FROM product p
                       WHERE p.status = 1 AND p.id IN (:ids) AND p.type = \'simple\' AND p.effective_price IS NOT NULL
                       UNION ALL
                       SELECT COALESCE(pova.sale_price, pova.price) AS price_val
                       FROM product_option_value_assignment pova
                       INNER JOIN product pv ON pv.id = pova.product_id
                       WHERE pv.status = 1 AND pv.id IN (:ids) AND pv.type = \'variable\' AND (pova.sale_price IS NOT NULL OR pova.price IS NOT NULL)
                       UNION ALL
                       SELECT p3.effective_price AS price_val
                       FROM product p3
                       WHERE p3.status = 1 AND p3.id IN (:ids) AND p3.type = \'variable_no_prices\' AND p3.effective_price IS NOT NULL
                     ) t',
                    ['ids' => $ids],
                    ['ids' => ArrayParameterType::INTEGER]
                ) ?: ['min_price' => null, 'max_price' => null];
                $initialFacets['price'] = [ 'type' => 'range', 'min' => $priceRow['min_price'] ?? null, 'max' => $priceRow['max_price'] ?? null ];
            }
        }

        return $this->render('catalog/search/index.html.twig', [
            'text' => $text,
            'total' => $total,
            'products' => $products,
            'page' => $page,
            'limit' => $currentLimit,
            'initial_facets_json' => json_encode(['facets' => $initialFacets, 'meta' => $meta], JSON_UNESCAPED_UNICODE),
        ]);
    }

    #[Route(path: '/search/products', name: 'catalog_search_products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        $text = trim((string)$request->query->get('text', ''));
        $raw = $request->query->all('f');

        // Параметры пагинации
        $allowedLimits = (array)($this->getParameter('app.pagination.allowed_limits') ?? [10, 20, 30]);
        $defaultLimit = (int)($this->getParameter('app.pagination.default_limit') ?? 20);
        $currentLimit = (int)$request->query->getInt('limit', $defaultLimit);
        if (!in_array($currentLimit, array_map('intval', $allowedLimits), true)) {
            $currentLimit = $defaultLimit;
        }
        $page = max(1, (int)$request->query->getInt('page', 1));

        $products = [];
        $total = 0;
        if ($text !== '') {
            $found = $this->productSearch->search($text, 5000, 0);
            $ids = array_map('intval', $found['ids'] ?? []);
            if (!empty($ids)) {
                // Построим условия фильтрации и получим список id после применения фасетов
                $qbIds = $this->productRepository->createQueryBuilder('p')
                    ->select('p.id')
                    ->andWhere('p.status = true')
                    ->andWhere('p.id IN (:ids)')->setParameter('ids', $ids);

                $i = 0;
                $numericCodes = ['height', 'bulbs_count', 'lighting_area'];
                // Дополнительные ограничения по цене на выборку ids
                $priceMinRaw = $request->query->get('price_min');
                $priceMaxRaw = $request->query->get('price_max');
                if ($priceMinRaw !== null && is_numeric((string)$priceMinRaw)) {
                    $i++;
                    $qbIds->andWhere('p.effectivePrice >= :q_price_min')->setParameter('q_price_min', (int)$priceMinRaw);
                }
                if ($priceMaxRaw !== null && is_numeric((string)$priceMaxRaw)) {
                    $i++;
                    $qbIds->andWhere('p.effectivePrice <= :q_price_max')->setParameter('q_price_max', (int)$priceMaxRaw);
                }

                foreach ($raw as $code => $csv) {
                    $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
                    if (empty($values)) { continue; }
                    $lower = strtolower((string)$code);
                    if (in_array($lower, $numericCodes, true)) {
                        $intVals = array_values(array_filter(array_map(static fn($v) => is_numeric($v) ? (int)$v : null, $values), static fn($v) => $v !== null));
                        if (empty($intVals)) { continue; }
                        $i++;
                        $valsParam = 'f_vals_' . $i;
                        // DQL использует имена свойств сущности, а не имена колонок
                        $prop = $lower === 'bulbs_count' ? 'bulbsCount' : ($lower === 'lighting_area' ? 'lightingArea' : 'height');
                        $existsNum = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pnum' . $i . ' WHERE pnum' . $i . '.product = p AND pnum' . $i . '.' . $prop . ' IN (:' . $valsParam . '))';
                        $qbIds->andWhere($existsNum)->setParameter($valsParam, $intVals);
                        continue;
                    }
                    if ($lower === 'category') {
                        $i++;
                        $valsParam = 'f_vals_' . $i;
                        $existsCat = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductToCategory pc' . $i . ' JOIN pc' . $i . '.category c' . $i . ' WHERE pc' . $i . '.product = p AND pc' . $i . '.isParent = true AND c' . $i . '.name IN (:' . $valsParam . '))';
                        $qbIds->andWhere($existsCat)->setParameter($valsParam, $values);
                        continue;
                    }
                    $i++;
                    $codeParam = 'f_code_' . $i;
                    $valsParam = 'f_vals_' . $i;
                    $existsAttr = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductAttributeAssignment paa' . $i . ' JOIN paa' . $i . '.attribute a' . $i . ' WHERE paa' . $i . '.product = p AND a' . $i . '.code = :' . $codeParam . ' AND paa' . $i . '.stringValue IN (:' . $valsParam . '))';
                    $existsOpt = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pova' . $i . ' JOIN pova' . $i . '.option o' . $i . ' JOIN pova' . $i . '.value ov' . $i . ' WHERE pova' . $i . '.product = p AND o' . $i . '.code = :' . $codeParam . ' AND ov' . $i . '.value IN (:' . $valsParam . '))';
                    $qbIds->andWhere('(' . $existsAttr . ' OR ' . $existsOpt . ')')
                        ->setParameter($codeParam, $code)
                        ->setParameter($valsParam, $values);
                }

                $rawIds = $qbIds->getQuery()->getScalarResult();
                $filteredIds = array_map(static fn($r) => (int)($r['id'] ?? $r[1] ?? $r[0] ?? 0), $rawIds);
                // Сохранить порядок релевантности согласно исходному $ids
                $idSet = array_flip($filteredIds);
                $orderedFiltered = [];
                foreach ($ids as $id) { if (isset($idSet[$id])) { $orderedFiltered[] = $id; } }

                $total = count($orderedFiltered);
                $offset = ($page - 1) * $currentLimit;
                $pagedIds = array_slice($orderedFiltered, $offset, $currentLimit);

                if (!empty($pagedIds)) {
                    $sort = (string)($request->query->get('sort') ?? 'relevance');
                    $qb = $this->productRepository->createQueryBuilder('p')
                        ->leftJoin('p.image', 'img')->addSelect('img')
                        ->andWhere('p.status = true')
                        ->andWhere('p.id IN (:ids)')->setParameter('ids', $pagedIds);
                    switch (strtolower($sort)) {
                        case 'popular':
                            $qb->orderBy('p.sortOrder', 'ASC');
                            break;
                        case 'price_asc':
                            $qb->orderBy('p.effectivePrice', 'ASC');
                            break;
                        case 'price_desc':
                            $qb->orderBy('p.effectivePrice', 'DESC');
                            break;
                        case 'date_asc':
                            $qb->orderBy('p.timestamps.createdAt', 'ASC');
                            break;
                        case 'date_desc':
                            $qb->orderBy('p.timestamps.createdAt', 'DESC');
                            break;
                        case 'name_asc':
                            $qb->orderBy('p.name', 'ASC');
                            break;
                        case 'name_desc':
                            $qb->orderBy('p.name', 'DESC');
                            break;
                        case 'relevance':
                        default:
                            $entities = $qb->getQuery()->getResult();
                            $byId = [];
                            foreach ($entities as $e) { $byId[$e->getId()] = $e; }
                            foreach ($pagedIds as $id) { if (isset($byId[$id])) { $products[] = $byId[$id]; } }
                            $entities = null;
                            break;
                    }
                    if (empty($products)) {
                        $entities = $qb->getQuery()->getResult();
                        $products = is_array($entities) ? $entities : [];
                    }
                }
            }
        }

        return $this->render('catalog/category/_grid.html.twig', [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'limit' => $currentLimit,
            // для унифицированной пагинации в шаблоне
            'routeName' => 'catalog_search_products',
            'routeParams' => [],
        ]);
    }
}


