<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductImage::class)]
final class ProductImageCacheListener
{
    private const FILTERS = ['sm', 'md', 'md2', 'xl'];

    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function postPersist(ProductImage $productImage, LifecycleEventArgs $event): void
    {
        $this->logger->info('Начинаем прогрев кеша для нового изображения', [
            'image_id' => $productImage->getId(),
            'image_url' => $productImage->getImageUrl(),
        ]);

        $imageUrl = $productImage->getImageUrl();
        if (!$imageUrl) {
            return;
        }

        // Извлекаем относительный путь из полного URL кеша
        $relativePath = $this->extractRelativePath($imageUrl);
        if (!$relativePath) {
            $this->logger->warning('Не удалось извлечь относительный путь из URL изображения', [
                'image_url' => $imageUrl,
            ]);
            return;
        }

        // Прогреваем кеш для всех фильтров
        foreach (self::FILTERS as $filter) {
            try {
                $this->warmupImageCache($relativePath, $filter);
                $this->logger->info('Кеш прогрет для фильтра', [
                    'filter' => $filter,
                    'path' => $relativePath,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Ошибка при прогреве кеша', [
                    'filter' => $filter,
                    'path' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function extractRelativePath(string $imageUrl): ?string
    {
        // Если URL содержит '/media/cache/', извлекаем путь после него
        if (str_contains($imageUrl, '/media/cache/')) {
            $parts = explode('/media/cache/', $imageUrl);
            if (count($parts) >= 2) {
                $cachePart = explode('/', $parts[1]);
                if (count($cachePart) >= 2) {
                    // Пропускаем filter и берем остальной путь
                    array_shift($cachePart); // удаляем filter
                    return implode('/', $cachePart);
                }
            }
        }

        // Если URL содержит '/img/', извлекаем путь после него
        if (str_contains($imageUrl, '/img/')) {
            $parts = explode('/img/', $imageUrl);
            if (count($parts) >= 2) {
                return 'img/' . $parts[1];
            }
        }

        return null;
    }

    private function warmupImageCache(string $relativePath, string $filter): void
    {
        $process = new Process([
            'php',
            'bin/console',
            'liip:imagine:cache:resolve',
            $relativePath,
            '--filter=' . $filter,
            '--force'
        ]);

        $process->setWorkingDirectory(getcwd());
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                sprintf('Failed to warmup cache for %s[%s]: %s', $relativePath, $filter, $process->getErrorOutput())
            );
        }
    }
}
