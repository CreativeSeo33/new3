<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Entity\FacetConfig;
use App\Repository\FacetConfigRepository;
use App\Service\Search\ProductSearch;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class FacetsController extends AbstractController
{
    public function __construct(
        private readonly Connection $db,
        private readonly FacetConfigRepository $configRepo,
        private readonly int $publicTtl,
        private readonly int $defaultValuesLimit,
        private readonly ProductSearch $productSearch,
    ) {}

    #[Route('/api/catalog/facets', name: 'catalog_facets', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $categoryId = (int)($request->query->get('category') ?? 0);
        $text = trim((string)($request->query->get('text') ?? ''));
        $ids = [];
        $isSearchMode = ($categoryId === 0 && $text !== '');
        if ($isSearchMode) {
            $found = $this->productSearch->search($text, 5000, 0);
            $ids = array_map('intval', $found['ids'] ?? []);
            if (empty($ids)) {
                return new JsonResponse(['facets' => []], 200, [ 'Cache-Control' => 'no-cache' ]);
            }
        }
        $config = $categoryId > 0
            ? $this->configRepo->findEffectiveConfigForCategory($categoryId)
            : $this->configRepo->findOneBy(['scope' => FacetConfig::SCOPE_GLOBAL]);

        $facets = [];
        $meta = [];

        // Determine facet codes to include: from config (preferred) or from facet_dictionary fallback
        $attributeCodes = [];
        $optionCodes = [];
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
        if (!$config || (empty($attributeCodes) && empty($optionCodes))) {
            // Fallback to facet_dictionary when no config present
            if ($categoryId > 0) {
                $row = $this->db->fetchAssociative(
                    'SELECT attributes_json, options_json FROM facet_dictionary WHERE category_id = :cid LIMIT 1',
                    ['cid' => $categoryId]
                );
            } else {
                $row = $this->db->fetchAssociative(
                    'SELECT attributes_json, options_json FROM facet_dictionary WHERE category_id IS NULL LIMIT 1'
                );
            }
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

        // If there is config and codes, enrich meta from config and fallback to DB names when needed
        if ($config && (!empty($attributeCodes) || !empty($optionCodes))) {
            $attrCfg = [];
            foreach ($config->getAttributes() as $a) {
                if (!empty($a['code'])) $attrCfg[strtolower((string)$a['code'])] = $a;
            }
            $optCfg = [];
            foreach ($config->getOptions() as $o) {
                if (!empty($o['code'])) $optCfg[strtolower((string)$o['code'])] = $o;
            }
            // DB names fallback
            if (!empty($attributeCodes)) {
                $rows = $this->db->fetchAllAssociative('SELECT code, name FROM attribute WHERE code IN (:codes)', [ 'codes' => $attributeCodes ], [ 'codes' => ArrayParameterType::STRING ]);
                $names = [];
                foreach ($rows as $r) $names[strtolower((string)$r['code'])] = (string)$r['name'];
                foreach ($attributeCodes as $code) {
                    $key = strtolower((string)$code);
                    $cfgItem = $attrCfg[$key] ?? null;
                    $label = is_array($cfgItem) && array_key_exists('label', $cfgItem) && $cfgItem['label'] !== null && $cfgItem['label'] !== ''
                        ? (string)$cfgItem['label']
                        : ($names[$key] ?? (string)$code);
                    $order = is_array($cfgItem) && array_key_exists('order', $cfgItem) && $cfgItem['order'] !== null ? (int)$cfgItem['order'] : null;
                    $meta[$code] = [ 'title' => $label, 'sort' => $order ];
                }
            }
            if (!empty($optionCodes)) {
                $rows = $this->db->fetchAllAssociative('SELECT code, name FROM `option` WHERE code IN (:codes)', [ 'codes' => $optionCodes ], [ 'codes' => ArrayParameterType::STRING ]);
                $names = [];
                foreach ($rows as $r) $names[strtolower((string)$r['code'])] = (string)$r['name'];
                foreach ($optionCodes as $code) {
                    $key = strtolower((string)$code);
                    $cfgItem = $optCfg[$key] ?? null;
                    $label = is_array($cfgItem) && array_key_exists('label', $cfgItem) && $cfgItem['label'] !== null && $cfgItem['label'] !== ''
                        ? (string)$cfgItem['label']
                        : ($names[$key] ?? (string)$code);
                    $order = is_array($cfgItem) && array_key_exists('order', $cfgItem) && $cfgItem['order'] !== null ? (int)$cfgItem['order'] : null;
                    $meta[$code] = [ 'title' => $label, 'sort' => $order ];
                }
            }
        }

        // Search-mode live discovery: если нет конфигурации и словаря — собрать коды из найденного поднабора ids
        if ($isSearchMode && empty($attributeCodes) && empty($optionCodes) && !empty($ids)) {
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

        foreach ($attributeCodes as $code) {
            $values = $this->countAttributeValues($categoryId, $code, $request, $ids);
            if ($this->defaultValuesLimit > 0 && count($values) > $this->defaultValuesLimit) {
                $values = array_slice($values, 0, $this->defaultValuesLimit);
            }
            $facets[$code] = ['type' => 'attribute', 'values' => $values];
        }

        foreach ($optionCodes as $code) {
            $values = $this->countOptionValues($categoryId, $code, $request, $ids);
            if ($this->defaultValuesLimit > 0 && count($values) > $this->defaultValuesLimit) {
                $values = array_slice($values, 0, $this->defaultValuesLimit);
            }
            $facets[$code] = ['type' => 'option', 'values' => $values];
        }

        // Фасет категорий в режиме поиска (по найденному подмножеству ids)
        if ($isSearchMode) {
            $catValues = $this->countCategoryValues($request, $ids);
            if ($this->defaultValuesLimit > 0 && count($catValues) > $this->defaultValuesLimit) {
                $catValues = array_slice($catValues, 0, $this->defaultValuesLimit);
            }
            $facets['category'] = ['type' => 'category', 'values' => $catValues];
            $meta['category'] = $meta['category'] ?? ['title' => 'Категории', 'sort' => null];
        }

        // Always include live price range
        if (!empty($ids)) {
            $priceRow = $this->db->fetchAssociative(
                'SELECT MIN(p.effective_price) AS min_price, MAX(p.effective_price) AS max_price
                 FROM product p
                 WHERE p.status = 1 AND p.id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]
            ) ?: ['min_price' => null, 'max_price' => null];
        } else {
            $priceRow = $this->db->fetchAssociative(
                'SELECT MIN(p.effective_price) AS min_price, MAX(p.effective_price) AS max_price
                 FROM product p INNER JOIN product_to_category pc ON pc.product_id = p.id
                 WHERE p.status = 1 AND pc.category_id = :cid', ['cid' => $categoryId]
            ) ?: ['min_price' => null, 'max_price' => null];
        }
        $facets['price'] = [ 'type' => 'range', 'min' => $priceRow['min_price'] ?? null, 'max' => $priceRow['max_price'] ?? null ];

        return new JsonResponse(['facets' => $facets, 'meta' => $meta], 200, [
            'Cache-Control' => 'public, max-age=' . $this->publicTtl,
        ]);
    }

    private function countAttributeValues(int $categoryId, string $attributeCode, Request $request, array $ids = []): array
    {
        // Build dynamic WHERE for selected filters f[code]=csv (excluding current code)
        [$joinSql, $params, $types] = $this->buildFacetFilterSql($request, $attributeCode);
        $base = 'SELECT paa.string_value AS code, paa.string_value AS label, COUNT(DISTINCT p.id) AS cnt
             FROM product_attribute_assignment paa
             INNER JOIN attribute a ON a.id = paa.attribute_id
             INNER JOIN product p ON p.id = paa.product_id';
        if (!empty($ids)) {
            $where = ' WHERE p.status = 1 AND a.code = :code AND paa.string_value IS NOT NULL AND p.id IN (:ids)';
            $params = array_merge($params, ['code' => $attributeCode, 'ids' => $ids]);
            $types['ids'] = ArrayParameterType::INTEGER;
            $sql = $base . $joinSql . $where . ' GROUP BY paa.string_value ORDER BY cnt DESC';
        } else {
            $sql = $base . ' INNER JOIN product_to_category pc ON pc.product_id = p.id' . $joinSql .
                ' WHERE p.status = 1 AND pc.category_id = :cid AND a.code = :code AND paa.string_value IS NOT NULL'
                . ' GROUP BY paa.string_value ORDER BY cnt DESC';
            $params = array_merge($params, ['cid' => $categoryId, 'code' => $attributeCode]);
        }
        $rows = $this->db->fetchAllAssociative($sql, $params, $types);
        return array_map(static fn(array $r) => [
            'code' => (string)$r['code'],
            'label' => (string)$r['label'],
            'count' => (int)$r['cnt'],
        ], $rows);
    }

    private function countOptionValues(int $categoryId, string $optionCode, Request $request, array $ids = []): array
    {
        [$joinSql, $params, $types] = $this->buildFacetFilterSql($request, $optionCode);

        // Поддержка числовых кодов, идущих из pova: height | bulbs_count | lighting_area
        $lower = strtolower($optionCode);
        if (in_array($lower, ['height', 'bulbs_count', 'lighting_area'], true)) {
            $col = $lower === 'bulbs_count' ? 'bulbs_count' : ($lower === 'lighting_area' ? 'lighting_area' : 'height');
            $base = 'SELECT CAST(pova.' . $col . ' AS CHAR) AS code, CAST(pova.' . $col . ' AS CHAR) AS label, COUNT(DISTINCT p.id) AS cnt
                 FROM product_option_value_assignment pova
                 INNER JOIN product p ON p.id = pova.product_id';
            if (!empty($ids)) {
                $where = ' WHERE p.status = 1 AND pova.' . $col . ' IS NOT NULL AND p.id IN (:ids)';
                $params = array_merge($params, ['ids' => $ids]);
                $types['ids'] = ArrayParameterType::INTEGER;
                $sql = $base . $joinSql . $where . ' GROUP BY pova.' . $col . ' ORDER BY cnt DESC';
            } else {
                $sql = $base . ' INNER JOIN product_to_category pc ON pc.product_id = p.id' . $joinSql .
                    ' WHERE p.status = 1 AND pc.category_id = :cid AND pova.' . $col . ' IS NOT NULL'
                    . ' GROUP BY pova.' . $col . ' ORDER BY cnt DESC';
                $params = array_merge($params, ['cid' => $categoryId]);
            }
            $rows = $this->db->fetchAllAssociative($sql, $params, $types);
            return array_map(static fn(array $r) => [
                'code' => (string)$r['code'],
                'label' => (string)$r['label'],
                'count' => (int)$r['cnt'],
            ], $rows);
        }

        $base = 'SELECT ov.code AS code, ov.value AS label, COUNT(DISTINCT p.id) AS cnt
             FROM product_option_value_assignment pova
             INNER JOIN `option` o ON o.id = pova.option_id
             INNER JOIN option_value ov ON ov.id = pova.value_id
             INNER JOIN product p ON p.id = pova.product_id';
        if (!empty($ids)) {
            $where = ' WHERE p.status = 1 AND o.code = :code AND p.id IN (:ids)';
            $params = array_merge($params, ['code' => $optionCode, 'ids' => $ids]);
            $types['ids'] = ArrayParameterType::INTEGER;
            $sql = $base . $joinSql . $where . ' GROUP BY ov.code, ov.value ORDER BY cnt DESC';
        } else {
            $sql = $base . ' INNER JOIN product_to_category pc ON pc.product_id = p.id' . $joinSql .
                ' WHERE p.status = 1 AND pc.category_id = :cid AND o.code = :code'
                . ' GROUP BY ov.code, ov.value ORDER BY cnt DESC';
            $params = array_merge($params, ['cid' => $categoryId, 'code' => $optionCode]);
        }
        $rows = $this->db->fetchAllAssociative($sql, $params, $types);
        return array_map(static fn(array $r) => [
            'code' => (string)$r['code'],
            'label' => (string)$r['label'],
            'count' => (int)$r['cnt'],
        ], $rows);
    }

    private function countCategoryValues(Request $request, array $ids = []): array
    {
        // Учитываем все выбранные фильтры, кроме самого category
        [$joinSql, $params, $types] = $this->buildFacetFilterSql($request, 'category');

        $base = 'SELECT c.id AS code, c.name AS label, COUNT(DISTINCT p.id) AS cnt
                 FROM product_to_category pc
                 INNER JOIN category c ON c.id = pc.category_id
                 INNER JOIN product p ON p.id = pc.product_id';

        if (!empty($ids)) {
            $where = ' WHERE p.status = 1 AND pc.is_parent = 1 AND p.id IN (:ids)';
            $params = array_merge($params, ['ids' => $ids]);
            $types['ids'] = ArrayParameterType::INTEGER;
            $sql = $base . $joinSql . $where . ' GROUP BY c.id, c.name ORDER BY cnt DESC';
        } else {
            // В режиме категории этот фасет не используется
            $sql = $base . ' WHERE 1=0';
        }

        $rows = $this->db->fetchAllAssociative($sql, $params, $types);
        return array_map(static fn(array $r) => [
            'code' => (string)$r['code'],
            'label' => (string)$r['label'],
            'count' => (int)$r['cnt'],
        ], $rows);
    }

    /**
     * @return array{0:string,1:array}
     */
    private function buildFacetFilterSql(Request $request, ?string $excludeCode = null): array
    {
        $raw = $request->query->all('f');
        $joins = '';
        $params = [];
        $types = [];
        $i = 0;
        $numericCodes = ['height', 'bulbs_count', 'lighting_area'];
        foreach ($raw as $code => $csv) {
            if ($excludeCode !== null && (string)$code === $excludeCode) continue;
            $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
            if (empty($values)) continue;

            $lower = strtolower((string)$code);
            if (in_array($lower, $numericCodes, true)) {
                // Числовые поля из product_option_value_assignment: height | bulbs_count | lighting_area
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
            $params[$codeParam] = (string)$code;
            $params[$valsParam] = $values;
            $types[$valsParam] = ArrayParameterType::STRING;
        }
        return [$joins, $params, $types];
    }
}


