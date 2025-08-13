<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ImageCacheService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Product;
use App\Entity\ProductImage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class MediaAdminController
{
    public function __construct(private readonly string $projectDir, private readonly ImageCacheService $imageCacheService, private readonly EntityManagerInterface $em)
    {
    }

    #[Route(path: '/api/admin/media/jpg-list', name: 'admin_media_jpg_list', methods: ['GET'])]
    public function listJpg(): JsonResponse
    {
        $publicImg = rtrim($this->projectDir, '\\/') . '/public/img';
        $items = [];
        if (is_dir($publicImg)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($publicImg, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if ($ext !== 'jpg' && $ext !== 'jpeg') {
                    continue;
                }
                $relative = ltrim(str_replace($publicImg, '', $file->getPathname()), '\\/');
                $relative = str_replace('\\', '/', $relative);
                $items[] = $relative;
            }
        }

        return new JsonResponse(['items' => $items]);
    }

    #[Route(path: '/admin/media/cache/{size}/generate', name: 'admin_media_cache_generate_size', requirements: ['size' => '\\d+x\\d+'], methods: ['POST'])]
    public function generateForSize(string $size): JsonResponse
    {
        [$width, $height] = array_map('intval', explode('x', $size, 2));
        $count = $this->imageCacheService->generateAllForSize($width, $height);
        return new JsonResponse(['generated' => $count, 'size' => $size]);
    }

    #[Route(path: '/api/admin/media/tree', name: 'admin_media_tree', methods: ['GET'])]
    public function listDirectoryTree(): JsonResponse
    {
        $base = rtrim($this->projectDir, '\/') . '/public/img';
        $build = function (string $absoluteDir, string $relativeDir) use (&$build): array {
            $nodes = [];
            $handle = @opendir($absoluteDir);
            if ($handle === false) {
                return $nodes;
            }
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $absoluteDir . '/' . $entry;
                if (is_dir($full)) {
                    $childRel = trim(($relativeDir === '' ? $entry : $relativeDir . '/' . $entry), '/');
                    $nodes[] = [
                        'name' => $entry,
                        'path' => $childRel,
                        'children' => $build($full, $childRel),
                    ];
                }
            }
            closedir($handle);
            usort($nodes, static fn(array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));
            return $nodes;
        };

        $tree = is_dir($base) ? $build($base, '') : [];
        return new JsonResponse(['tree' => $tree]);
    }

    #[Route(path: '/api/admin/media/list', name: 'admin_media_list', methods: ['GET'])]
    public function listImagesInDirectory(Request $request): JsonResponse
    {
        $rel = (string) $request->query->get('dir', '');
        $rel = trim(str_replace(['..', '\\'], ['', '/'], $rel), '/');
        $base = rtrim($this->projectDir, '\\/') . '/public/img';
        $dir = $rel === '' ? $base : $base . '/' . $rel;

        $baseReal = realpath($base) ?: $base;
        $dirReal = realpath($dir) ?: $dir;
        $baseReal = str_replace('\\', '/', $baseReal);
        $dirReal = str_replace('\\', '/', $dirReal);
        if (!str_starts_with($dirReal, $baseReal) || !is_dir($dirReal)) {
            return new JsonResponse(['items' => [], 'dir' => $rel]);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $items = [];
        $handle = @opendir($dirReal);
        if ($handle !== false) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $dirReal . '/' . $entry;
                if (is_file($full)) {
                    $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowed, true)) {
                        continue;
                    }
                    $relative = ltrim(($rel === '' ? '' : $rel . '/') . $entry, '/');
                    $items[] = [
                        'name' => $entry,
                        'relative' => $relative,
                        'url' => '/img/' . $relative,
                    ];
                }
            }
            closedir($handle);
        }
        // sort by name
        usort($items, static fn(array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        return new JsonResponse(['items' => $items, 'dir' => $rel]);
    }

    #[Route(path: '/api/admin/media/product/{id}/images', name: 'admin_media_attach_images', methods: ['POST'])]
    public function attachImagesToProduct(int $id, Request $request): JsonResponse
    {
        /** @var Product|null $product */
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if (!$items) {
            return new JsonResponse(['created' => 0, 'items' => []]);
        }

        $normalize = static function (string $path): string {
            $path = str_replace(['\\', '..'], ['/', ''], $path);
            return trim($path, '/');
        };

        $qb = $this->em->createQueryBuilder();
        $maxSort = (int) ($qb->select('COALESCE(MAX(pi.sortOrder), 0)')
            ->from(ProductImage::class, 'pi')
            ->where('pi.product = :p')
            ->setParameter('p', $product)
            ->getQuery()->getSingleScalarResult());

        $created = [];
        foreach ($items as $rel) {
            if (!is_string($rel) || $rel === '') {
                continue;
            }
            $relative = $normalize($rel);
            // ensure cache 500x500 and compute cached URL
            try {
                $this->imageCacheService->ensureCached($relative, 500, 500);
            } catch (\Throwable $e) {
                // skip invalid source silently
                continue;
            }
            $cachedUrl = '/media/cache/500x500/' . $relative;

            $pi = new ProductImage();
            $pi->setProduct($product)
               ->setImageUrl($cachedUrl)
               ->setSortOrder(++$maxSort);
            $this->em->persist($pi);
            $created[] = [
                'imageUrl' => $cachedUrl,
                'sortOrder' => $maxSort,
            ];
        }
        $this->em->flush();

        return new JsonResponse(['created' => count($created), 'items' => $created]);
    }

    #[Route(path: '/api/admin/media/product-image/{id}', name: 'admin_media_delete_product_image', methods: ['DELETE'])]
    public function deleteProductImage(int $id): JsonResponse
    {
        /** @var ProductImage|null $pi */
        $pi = $this->em->getRepository(ProductImage::class)->find($id);
        if (!$pi) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }
        $this->em->remove($pi);
        $this->em->flush();
        return new JsonResponse(['deleted' => $id]);
    }
}


