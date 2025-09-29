<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\FacetConfigRepository;
use App\Service\FacetIndexer;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FacetController extends AbstractController
{
    public function __construct(
        private readonly Connection $db,
        private readonly FacetIndexer $indexer,
        private readonly FacetConfigRepository $configRepo,
        private readonly int $cacheTtl = 90,
    ) {}

    #[Route('/api/admin/facets/available', name: 'admin_facets_available', methods: ['GET'])]
    public function available(Request $request): Response
    {
        $categoryId = $request->query->get('category');
        $categoryId = $categoryId !== null ? (int)$categoryId : null;

        $row = $this->db->fetchAssociative(
            'SELECT category_id, attributes_json, options_json, price_min, price_max, updated_at
             FROM facet_dictionary
             WHERE ' . ($categoryId ? 'category_id = :cid' : 'category_id IS NULL') . ' LIMIT 1',
            $categoryId ? ['cid' => $categoryId] : []
        );

        if (!$row) {
            return $this->json(['message' => 'not_found'], 404);
        }

        $etag = 'W/"fd-' . substr(sha1((string)($row['category_id'] ?? 'global') . '|' . ($row['updated_at'] ?? '')), 0, 16) . '"';
        $ifNoneMatch = (string)($request->headers->get('If-None-Match') ?? '');
        if ($ifNoneMatch === $etag) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=' . $this->cacheTtl,
            ]);
        }

        $attributes = json_decode($row['attributes_json'] ?? '[]', true) ?: [];
        $options = json_decode($row['options_json'] ?? '[]', true) ?: [];
        $payload = [
            'categoryId' => $row['category_id'] ?? null,
            'attributes' => $attributes['items'] ?? [],
            'options' => $options,
            'price' => [
                'min' => $row['price_min'] !== null ? (int)$row['price_min'] : null,
                'max' => $row['price_max'] !== null ? (int)$row['price_max'] : null,
            ],
            'updatedAt' => $row['updated_at'],
        ];

        return new JsonResponse($payload, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=' . $this->cacheTtl,
        ]);
    }

    #[Route('/api/admin/facets/reindex', name: 'admin_facets_reindex', methods: ['POST'])]
    public function reindex(Request $request): Response
    {
        $target = (string)($request->query->get('category') ?? 'all');
        $attributes = $request->toArray()['attributes'] ?? null;
        $options = $request->toArray()['options'] ?? null;

        $attrCodes = is_array($attributes) ? array_values(array_filter(array_map('strval', $attributes))) : [];
        $optCodes = is_array($options) ? array_values(array_filter(array_map('strval', $options))) : [];

        if ($target === 'all') {
            // Перестроить для всех категорий; если переданы списки кодов — применим фильтры к каждой категории
            $categoryIds = $this->configRepo->getEntityManager()->getConnection()->fetchFirstColumn('SELECT DISTINCT category_id FROM product_to_category');
            foreach ($categoryIds as $cid) {
                $id = (int)$cid;
                if ($id > 0) {
                    $this->indexer->reindexCategory($id, $attrCodes, $optCodes);
                }
            }
            return $this->json(['status' => 'accepted'], 202);
        }
        $cid = (int)$target;
        $this->indexer->reindexCategory($cid, $attrCodes, $optCodes);
        return $this->json(['status' => 'accepted', 'categoryId' => $cid], 202);
    }
}


