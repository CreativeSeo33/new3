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
                    if (!empty($a['code'])) $attributeCodes[] = (string)$a['code'];
                }
                foreach ($opts as $o) {
                    if (!empty($o['code'])) $optionCodes[] = (string)$o['code'];
                }
                $attributeCodes = array_values(array_unique($attributeCodes));
                $optionCodes = array_values(array_unique($optionCodes));
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

        return new JsonResponse(['facets' => $facets], 200, [
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
        foreach ($raw as $code => $csv) {
            if ($excludeCode !== null && (string)$code === $excludeCode) continue;
            $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
            if (empty($values)) continue;
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


