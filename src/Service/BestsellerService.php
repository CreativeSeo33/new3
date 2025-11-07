<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Repository\BestsellerRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class BestsellerService
{
    private const CACHE_KEY = 'catalog.bestsellers';

    public function __construct(
        private BestsellerRepository $bestsellerRepository,
        private CacheInterface $cache,
        private int $cacheTtl
    ) {}

    /**
     * Получить бестселлеры с кешированием.
     *
     * @return Product[]
     */
    public function getCachedBestsellers(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter($this->cacheTtl);
            return $this->bestsellerRepository->getBestsellersWithProducts();
        });
    }

    /**
     * Инвалидировать кеш бестселлеров.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}


