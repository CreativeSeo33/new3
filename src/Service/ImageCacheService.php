<?php
declare(strict_types=1);

namespace App\Service;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Symfony\Component\Filesystem\Filesystem;

class ImageCacheService
{
    private string $publicDir;
    private Filesystem $filesystem;
    private CacheManager $cacheManager;
    private FilterManager $filterManager;
    private FilterConfiguration $filterConfig;
    private DataManager $dataManager;

    public function __construct(CacheManager $cacheManager, FilterManager $filterManager, FilterConfiguration $filterConfig, DataManager $dataManager, string $projectDir)
    {
        $this->filesystem = new Filesystem();
        $this->cacheManager = $cacheManager;
        $this->filterManager = $filterManager;
        $this->filterConfig = $filterConfig;
        $this->dataManager = $dataManager;
        $this->publicDir = rtrim($projectDir, '\\/') . '/public';
    }

    public function getCachedPathFor(string $relativeImgPath, int $width, int $height): string
    {
        $normalizedPath = ltrim($this->normalizeRelativePath($relativeImgPath), '/');
        return sprintf('%s/media/cache/%dx%d/img/%s', $this->publicDir, $width, $height, $normalizedPath);
    }

    public function getCachedPathForFilter(string $relativeImgPath, string $filterName): string
    {
        $normalizedPath = ltrim($this->normalizeRelativePath($relativeImgPath), '/');
        return sprintf('%s/media/cache/%s/img/%s', $this->publicDir, $filterName, $normalizedPath);
    }

    public function ensureCached(string $relativeImgPath, int $width, int $height): string
    {
        $normalized = ltrim($this->normalizeRelativePath($relativeImgPath), '/');
        $sourcePath = $this->publicDir . '/img/' . $normalized;
        $targetPath = $this->getCachedPathFor($relativeImgPath, $width, $height);

        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Source image not found: ' . $sourcePath);
        }

        if ($this->filesystem->exists($targetPath)) {
            $srcMtime = filemtime($sourcePath) ?: 0;
            $dstMtime = filemtime($targetPath) ?: 0;
            if ($dstMtime >= $srcMtime) {
                return $targetPath;
            }
        }

        $filterName = sprintf('%dx%d', $width, $height);
        $runtimeConfig = [
            'filters' => [
                'thumbnail' => [
                    'size' => [ $width, $height ],
                    'mode' => 'inset',
                    'allow_upscale' => true,
                ],
                'scale' => [
                    'dim' => [ $width, $height ],
                ],
                'background' => [
                    'size' => [ $width, $height ],
                    'position' => 'center',
                    'color' => '#fff',
                ],
            ],
        ];

        // Регистрируем/обновляем временный фильтр под именем WxH
        $this->filterConfig->set($filterName, $runtimeConfig);

        $publicSource = '/img/' . $normalized;
        $binary = $this->dataManager->find($filterName, $publicSource);
        $filtered = $this->filterManager->applyFilter($binary, $filterName);
        $this->cacheManager->store($filtered, $publicSource, $filterName);

        return $targetPath;
    }

    public function ensureCachedByFilter(string $relativeImgPath, string $filterName): string
    {
        $normalized = ltrim($this->normalizeRelativePath($relativeImgPath), '/');
        $sourcePath = $this->publicDir . '/img/' . $normalized;
        $targetPath = $this->getCachedPathForFilter($relativeImgPath, $filterName);

        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Source image not found: ' . $sourcePath);
        }

        if ($this->filesystem->exists($targetPath)) {
            $srcMtime = filemtime($sourcePath) ?: 0;
            $dstMtime = filemtime($targetPath) ?: 0;
            if ($dstMtime >= $srcMtime) {
                return $targetPath;
            }
        }

        $publicSource = '/img/' . $normalized;
        $binary = $this->dataManager->find($filterName, $publicSource);
        $filtered = $this->filterManager->applyFilter($binary, $filterName);
        $this->cacheManager->store($filtered, $publicSource, $filterName);

        return $targetPath;
    }

    public function generateAllForSize(int $width, int $height): int
    {
        $base = $this->publicDir . '/img';
        if (!is_dir($base)) {
            return 0;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        $count = 0;
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                continue;
            }
            $relative = ltrim(str_replace($base, '', $file->getPathname()), '\\/');
            $relative = str_replace('\\', '/', $relative);
            $this->ensureCached($relative, $width, $height);
            $count++;
        }
        return $count;
    }

    /**
     * Безопасная нормализация относительного пути внутри /public/img
     */
    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace(['\\', '..'], ['/', ''], $path);
        return trim($path, '/');
    }
}


