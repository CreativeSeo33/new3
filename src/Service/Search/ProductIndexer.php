<?php
declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use TeamTNT\TNTSearch\TNTSearch;

final class ProductIndexer
{
    private const INDEX_NAME = 'products.index';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LockFactory $lockFactory,
        private readonly TNTSearchFactory $tntFactory,
        private readonly RuQueryNormalizer $normalizer
    ) {}

    public function reindexAll(): int
    {
        $lock = $this->lockFactory->createLock('search.products.reindex');
        if (!$lock->acquire()) {
            return 0;
        }
        try {
            $tnt = $this->tntFactory->create();
            $indexer = $tnt->createIndex(self::INDEX_NAME);
            $indexer->setPrimaryKey('id');

            $conn = $this->em->getConnection();
            $stmt = $conn->executeQuery('SELECT id FROM product');
            $count = 0;
            while ($row = $stmt->fetchAssociative()) {
                $id = (int)$row['id'];
                $p = $this->em->getRepository(Product::class)->find($id);
                if ($p === null) { continue; }
                $doc = $this->buildDocument($p);
                $indexer->insert($doc);
                $count++;
            }
            return $count;
        } finally {
            $lock->release();
        }
    }

    public function upsert(Product $product): void
    {
        $tnt = $this->tntFactory->create();
        $tnt->selectIndex(self::INDEX_NAME);
        $index = $tnt->getIndex();
        $doc = $this->buildDocument($product);
        $index->update($product->getId() ?? 0, $doc);
    }

    public function delete(int $id): void
    {
        $tnt = $this->tntFactory->create();
        $tnt->selectIndex(self::INDEX_NAME);
        $index = $tnt->getIndex();
        $index->delete($id);
    }

    /**
     * @return array{id:int, searchableText:string}
     */
    private function buildDocument(Product $p): array
    {
        $name = (string)($p->getName() ?? '');
        $desc = (string)($p->getDescription() ?? '');
        $cats = [];
        foreach ($p->getCategory() as $pc) {
            $n = $pc->getCategory()?->getName();
            if ($n !== null) { $cats[] = $n; }
        }
        $catStr = implode(' ', $cats);

        $attrs = [];
        foreach ($p->getAttributeAssignments() as $a) {
            $label = $a->getStringValue() ?? $a->getValue()?->getValue();
            if ($label !== null) { $attrs[] = $label; }
        }
        $attrStr = implode(' ', $attrs);

        $text = str_repeat($name.' ', 3)
            . str_repeat($catStr.' ', 2)
            . $attrStr.' '
            . $desc;

        return [
            'id' => (int) $p->getId(),
            'searchableText' => $this->normalizer->normalize($text),
        ];
    }
}


