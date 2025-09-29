<?php
declare(strict_types=1);

namespace App\Service\Search;

use TeamTNT\TNTSearch\TNTSearch;

final class ProductSearch
{
    private const INDEX_NAME = 'products.index';

    public function __construct(
        private readonly TNTSearchFactory $tntFactory,
        private readonly RuQueryNormalizer $normalizer
    ) {}

    /**
     * @return array{ids:int[], scores:array<int,float>, total:int}
     */
    public function search(string $rawQuery, int $limit, int $offset = 0): array
    {
        $q = trim($rawQuery);
        if ($q === '') {
            return ['ids' => [], 'scores' => [], 'total' => 0];
        }

        $tnt = $this->tntFactory->create();
        $tnt->selectIndex(self::INDEX_NAME);

        $isPrefix = mb_strlen($q) < 5; // короткие запросы как prefix
        $norm = $this->normalizer->normalize($q, $isPrefix);

        $res = $tnt->search($norm, $limit + $offset);
        $ids = array_map('intval', $res['ids'] ?? []);
        $scores = $res['scores'] ?? [];
        $total = (int)($res['hits'] ?? count($ids));

        if ($offset > 0) {
            $ids = array_slice($ids, $offset, $limit);
        } else {
            $ids = array_slice($ids, 0, $limit);
        }

        return ['ids' => $ids, 'scores' => $scores, 'total' => $total];
    }
}


