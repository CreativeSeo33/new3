<?php
declare(strict_types=1);

namespace App\Controller\Media;

use App\Service\ImageCacheService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class ImageCacheController
{
    public function __construct(private readonly ImageCacheService $imageCacheService)
    {
    }

    #[Route(path: '/media/cache/{size}/{path}', name: 'media_image_cache', requirements: ['size' => '\\d+x\\d+', 'path' => '.+'], methods: ['GET'])]
    public function getCached(string $size, string $path, Request $request): BinaryFileResponse
    {
        [$width, $height] = array_map('intval', explode('x', $size, 2));
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Invalid size. Expected WxH.');
        }

        // path — относительный путь внутри public/img, например `catalog/a/b.jpg`
        $absoluteCachedPath = $this->imageCacheService->ensureCached($path, $width, $height);

        $response = new BinaryFileResponse($absoluteCachedPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($absoluteCachedPath));
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->setPublic();
        $response->setMaxAge(60 * 60 * 24 * 30);
        $response->setSharedMaxAge(60 * 60 * 24 * 30);
        return $response;
    }
}


