<?php
declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FacetConfigRepository;

final class FacetIndexer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly FacetConfigRepository $configRepo,
    ) {}

    public function reindexCategory(int $categoryId, ?array $attributeCodes = null, ?array $optionCodes = null): void
    {
        // Price range
        // simple: use product.effective_price
        // variable: use each variant assignment price with sale priority (COALESCE(pova.sale_price, pova.price))
        $priceRow = $this->db->fetchAssociative(
             'SELECT MIN(t.price_val) AS min_price, MAX(t.price_val) AS max_price
             FROM (
               SELECT p.effective_price AS price_val
               FROM product p
               INNER JOIN product_to_category pc ON pc.product_id = p.id
               WHERE pc.category_id = :cid AND p.status = 1 AND p.type = \'simple\' AND p.effective_price IS NOT NULL AND p.quantity > 0
               UNION ALL
               SELECT COALESCE(pova.sale_price, pova.price) AS price_val
               FROM product_option_value_assignment pova
               INNER JOIN product p2 ON p2.id = pova.product_id
               INNER JOIN product_to_category pc2 ON pc2.product_id = pova.product_id
               WHERE pc2.category_id = :cid AND p2.status = 1 AND (pova.sale_price IS NOT NULL OR pova.price IS NOT NULL) AND p2.type = \'variable\' AND pova.quantity > 0
             ) t',
            ['cid' => $categoryId]
        ) ?: ['min_price' => null, 'max_price' => null];

        // Determine filter modes and normalize codes (null = not provided => include all; empty array = provided but none => include none)
        $attrMode = $attributeCodes === null ? 'all' : (empty($attributeCodes) ? 'none' : 'codes');
        $optMode = $optionCodes === null ? 'all' : (empty($optionCodes) ? 'none' : 'codes');
        $attributeCodes = $attributeCodes === null ? null : array_values(array_unique(array_map(static fn($v) => strtolower((string)$v), $attributeCodes)));
        $optionCodes = $optionCodes === null ? null : array_values(array_unique(array_map(static fn($v) => strtolower((string)$v), $optionCodes)));

        // Load effective config to apply label and sort overrides
        $cfg = $this->configRepo->findEffectiveConfigForCategory($categoryId);
        $attrCfgItems = is_array($cfg?->getAttributes()) ? $cfg->getAttributes() : [];
        $optCfgItems = is_array($cfg?->getOptions()) ? $cfg->getOptions() : [];
        $attrCfgMap = [];
        foreach ($attrCfgItems as $i) {
            if (!is_array($i)) continue;
            $code = strtolower((string)($i['code'] ?? ''));
            if ($code === '') continue;
            $attrCfgMap[$code] = [
                'label' => array_key_exists('label', $i) ? ($i['label'] !== null ? (string)$i['label'] : null) : null,
                'order' => array_key_exists('order', $i) && $i['order'] !== null ? (int)$i['order'] : null,
                'enabled' => (bool)($i['enabled'] ?? false),
            ];
        }
        $optCfgMap = [];
        foreach ($optCfgItems as $i) {
            if (!is_array($i)) continue;
            $code = strtolower((string)($i['code'] ?? ''));
            if ($code === '') continue;
            $optCfgMap[$code] = [
                'label' => array_key_exists('label', $i) ? ($i['label'] !== null ? (string)$i['label'] : null) : null,
                'order' => array_key_exists('order', $i) && $i['order'] !== null ? (int)$i['order'] : null,
                'enabled' => (bool)($i['enabled'] ?? false),
            ];
        }

        // Attributes distinct with min/max for numeric (with optional filter)
        $attrSql = 'SELECT a.code AS code, a.name AS name,
                    MIN(paa.int_value) AS min_int,
                    MAX(paa.int_value) AS max_int,
                    MIN(paa.decimal_value) AS min_dec,
                    MAX(paa.decimal_value) AS max_dec,
                    MAX(paa.data_type) AS data_type
             FROM product_attribute_assignment paa
             INNER JOIN attribute a ON a.id = paa.attribute_id
             INNER JOIN product_to_category pc ON pc.product_id = paa.product_id
             INNER JOIN product p ON p.id = paa.product_id
             WHERE pc.category_id = :cid AND p.status = 1
               AND (
                 (p.type = \'simple\' AND p.quantity > 0)
                 OR (p.type = \'variable\' AND EXISTS (
                      SELECT 1 FROM product_option_value_assignment pova_stock
                      WHERE pova_stock.product_id = p.id AND pova_stock.quantity > 0
                 ))
               )';
        $attrParams = ['cid' => $categoryId];
        $attrTypes = [];
        if ($attrMode === 'codes') {
            $attrSql .= ' AND a.code IN (:acodes)';
            $attrParams['acodes'] = $attributeCodes;
            $attrTypes['acodes'] = ArrayParameterType::STRING;
        }
        $attributes = [];
        if ($attrMode !== 'none') {
            $attrSql .= ' GROUP BY a.code, a.name';
            $attributes = $this->db->fetchAllAssociative($attrSql, $attrParams, $attrTypes);
        }

        // Options with values (with optional filter)
        $optSql = 'SELECT o.code AS code, o.name AS name, ov.code AS value_code, ov.value AS value_label,
                    MIN(pova.height) AS min_height,
                    MAX(pova.height) AS max_height,
                    MIN(pova.bulbs_count) AS min_bulbs,
                    MAX(pova.bulbs_count) AS max_bulbs,
                    MIN(pova.lighting_area) AS min_area,
                    MAX(pova.lighting_area) AS max_area
             FROM product_option_value_assignment pova
             INNER JOIN `option` o ON o.id = pova.option_id
             INNER JOIN option_value ov ON ov.id = pova.value_id
             INNER JOIN product_to_category pc ON pc.product_id = pova.product_id
             INNER JOIN product p ON p.id = pova.product_id
             WHERE pc.category_id = :cid AND p.status = 1 AND pova.quantity > 0';
        $optParams = ['cid' => $categoryId];
        $optTypes = [];
        if ($optMode === 'codes') {
            $optSql .= ' AND o.code IN (:ocodes)';
            $optParams['ocodes'] = $optionCodes;
            $optTypes['ocodes'] = ArrayParameterType::STRING;
        }
        $options = [];
        if ($optMode !== 'none') {
            $optSql .= ' GROUP BY o.code, o.name, ov.code, ov.value';
            $options = $this->db->fetchAllAssociative($optSql, $optParams, $optTypes);
        }

        $attributesJson = [
            'items' => array_map(function (array $row) use ($attrCfgMap): array {
                $type = $row['data_type'] ?? 'string';
                $min = null; $max = null;
                if ($type === 'int') { $min = (int)($row['min_int'] ?? 0); $max = (int)($row['max_int'] ?? 0); }
                if ($type === 'decimal') { $min = (float)($row['min_dec'] ?? 0); $max = (float)($row['max_dec'] ?? 0); }
                $code = (string)$row['code'];
                $cfg = $attrCfgMap[strtolower($code)] ?? null;
                $label = $cfg['label'] ?? null;
                $sort = $cfg['order'] ?? null;
                return [
                    'code' => $code,
                    'name' => ($label !== null && $label !== '') ? $label : (string)$row['name'],
                    'type' => $type,
                    'min' => $min,
                    'max' => $max,
                    'sort' => $sort,
                ];
            }, $attributes)
        ];

        $optMap = [];
        foreach ($options as $row) {
            $code = (string)$row['code'];
            if (!isset($optMap[$code])) {
                $cfg = $optCfgMap[strtolower($code)] ?? null;
                $label = $cfg['label'] ?? null;
                $sort = $cfg['order'] ?? null;
                $optMap[$code] = [
                    'code' => $code,
                    'name' => ($label !== null && $label !== '') ? $label : (string)$row['name'],
                    'sort' => $sort,
                    'values' => []
                ];
            }
            $optMap[$code]['values'][] = [
                'code' => (string)$row['value_code'],
                'label' => (string)$row['value_label'],
            ];
        }
        $optionsJson = array_values($optMap);

        $this->db->beginTransaction();
        try {
            // Upsert facet_dictionary
            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            // Try update first
            $affected = $this->db->executeStatement(
                'UPDATE facet_dictionary
                 SET attributes_json = :attrs, options_json = :opts,
                     price_min = :pmin, price_max = :pmax, updated_at = :updated
                 WHERE category_id = :cid',
                [
                    'attrs' => json_encode($attributesJson, JSON_UNESCAPED_UNICODE),
                    'opts' => json_encode($optionsJson, JSON_UNESCAPED_UNICODE),
                    'pmin' => $priceRow['min_price'] ?? null,
                    'pmax' => $priceRow['max_price'] ?? null,
                    'updated' => $now,
                    'cid' => $categoryId,
                ]
            );

            if ($affected === 0) {
                $this->db->executeStatement(
                    'INSERT INTO facet_dictionary (category_id, attributes_json, options_json, price_min, price_max, updated_at)
                     VALUES (:cid, :attrs, :opts, :pmin, :pmax, :updated)',
                    [
                        'cid' => $categoryId,
                        'attrs' => json_encode($attributesJson, JSON_UNESCAPED_UNICODE),
                        'opts' => json_encode($optionsJson, JSON_UNESCAPED_UNICODE),
                        'pmin' => $priceRow['min_price'] ?? null,
                        'pmax' => $priceRow['max_price'] ?? null,
                        'updated' => $now,
                    ]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reindexAll(): void
    {
        $categoryIds = $this->db->fetchFirstColumn('SELECT DISTINCT category_id FROM product_to_category');
        foreach ($categoryIds as $cid) {
            $id = (int)$cid;
            if ($id > 0) {
                $this->reindexCategory($id);
            }
        }
    }
}


