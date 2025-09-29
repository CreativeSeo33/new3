<?php
declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class FacetIndexer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
    ) {}

    public function reindexCategory(int $categoryId): void
    {
        // Price range
        $priceRow = $this->db->fetchAssociative(
            'SELECT MIN(p.effective_price) AS min_price, MAX(p.effective_price) AS max_price
             FROM product p
             INNER JOIN product_to_category pc ON pc.product_id = p.id
             WHERE pc.category_id = :cid AND p.status = 1',
            ['cid' => $categoryId]
        ) ?: ['min_price' => null, 'max_price' => null];

        // Attributes distinct with min/max for numeric
        $attributes = $this->db->fetchAllAssociative(
            'SELECT a.code AS code, a.name AS name,
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
             GROUP BY a.code, a.name'
            , ['cid' => $categoryId]
        );

        // Options with values
        $options = $this->db->fetchAllAssociative(
            'SELECT o.code AS code, o.name AS name, ov.code AS value_code, ov.value AS value_label,
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
             WHERE pc.category_id = :cid AND p.status = 1
             GROUP BY o.code, o.name, ov.code, ov.value'
            , ['cid' => $categoryId]
        );

        $attributesJson = [
            'items' => array_map(static function (array $row): array {
                $type = $row['data_type'] ?? 'string';
                $min = null; $max = null;
                if ($type === 'int') { $min = (int)($row['min_int'] ?? 0); $max = (int)($row['max_int'] ?? 0); }
                if ($type === 'decimal') { $min = (float)($row['min_dec'] ?? 0); $max = (float)($row['max_dec'] ?? 0); }
                return [
                    'code' => (string)$row['code'],
                    'name' => (string)$row['name'],
                    'type' => $type,
                    'min' => $min,
                    'max' => $max,
                ];
            }, $attributes)
        ];

        $optMap = [];
        foreach ($options as $row) {
            $code = (string)$row['code'];
            if (!isset($optMap[$code])) {
                $optMap[$code] = [
                    'code' => $code,
                    'name' => (string)$row['name'],
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


