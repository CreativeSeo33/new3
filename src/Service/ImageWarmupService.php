<?php
declare(strict_types=1);

namespace App\Service;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;

class ImageWarmupService
{
    public function __construct(
        private readonly DataManager $dataManager,
        private readonly FilterManager $filterManager,
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * Прогревает кэш LiipImagine для списка фильтров.
     * $relativeImgPath — относительный путь внутри /public/img, например: "catalog/a/b.jpg"
     */
    public function warm(string $relativeImgPath, array $filters): void
    {
        $normalized = ltrim(str_replace(['\\', '..'], ['/', ''], $relativeImgPath), '/');
        $publicSource = '/img/' . $normalized;

        foreach ($filters as $filterName) {
            if (!is_string($filterName) || $filterName === '') {
                continue;
            }
            try {
                $binary = $this->dataManager->find($filterName, $publicSource);
                $filtered = $this->filterManager->applyFilter($binary, $filterName);
                $this->cacheManager->store($filtered, $publicSource, $filterName);
            } catch (\Throwable) {
                // молча пропускаем проблемы с отдельными файлами/фильтрами
                continue;
            }
        }
    }

    /**
     * Удаляет из кэша все деривативы для указанного относительного пути и набора фильтров.
     */
    public function clear(string $relativeImgPath, array $filters): void
    {
        $normalized = ltrim(str_replace(['\\', '..'], ['/', ''], $relativeImgPath), '/');
        $publicSource = '/img/' . $normalized;
        try {
            $this->cacheManager->remove([$publicSource], $filters);
        } catch (\Throwable) {
            // игнорируем ошибки удаления
        }
    }
}


