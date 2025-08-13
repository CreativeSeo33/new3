<?php
declare(strict_types=1);

namespace App\Service;

use Intervention\Image\Interfaces\ImageManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageCacheService
{
    private string $publicDir;
    private ImageManagerInterface $imageManager;
    private Filesystem $filesystem;

    public function __construct(ImageManagerInterface $imageManager, string $projectDir)
    {
        $this->imageManager = $imageManager;
        $this->filesystem = new Filesystem();
        $this->publicDir = rtrim($projectDir, '\\/') . '/public';
    }

    public function getCachedPathFor(string $relativeImgPath, int $width, int $height): string
    {
        $normalizedPath = ltrim($this->normalizeRelativePath($relativeImgPath), '/');
        return sprintf('%s/media/cache/%dx%d/%s', $this->publicDir, $width, $height, $normalizedPath);
    }

    public function ensureCached(string $relativeImgPath, int $width, int $height): string
    {
        $sourcePath = $this->publicDir . '/img/' . ltrim($this->normalizeRelativePath($relativeImgPath), '/');
        $targetPath = $this->getCachedPathFor($relativeImgPath, $width, $height);

        if (!is_file($sourcePath)) {
            throw new \RuntimeException('Source image not found: ' . $sourcePath);
        }

        $targetDir = \dirname($targetPath);
        if (!$this->filesystem->exists($targetDir)) {
            $this->filesystem->mkdir($targetDir, 0755);
        }

        if ($this->filesystem->exists($targetPath)) {
            $srcMtime = filemtime($sourcePath) ?: 0;
            $dstMtime = filemtime($targetPath) ?: 0;
            if ($dstMtime >= $srcMtime) {
                return $targetPath;
            }
        }

        $image = $this->imageManager->read($sourcePath);
        // Вписываем в рамку WxH без апскейла и сохраняем в JPEG
        if (method_exists($image, 'contain')) {
            $image = $image->contain($width, $height);
        } else {
            // fallback для разных драйверов/версий: масштаб по ширине/высоте
            $image = $image->scale(width: $width, height: $height);
        }
        $image = $image->toJpeg(85);
        $image->save($targetPath);

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
            if ($ext !== 'jpg' && $ext !== 'jpeg') {
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


