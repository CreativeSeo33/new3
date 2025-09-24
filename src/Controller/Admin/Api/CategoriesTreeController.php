<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CategoriesTreeController extends AbstractController
{
    #[Route('/api/admin/categories/tree', name: 'admin_api_categories_tree', methods: ['GET'])]
    public function __invoke(Request $request, CategoryRepository $categoryRepository): Response
    {
        $all = $categoryRepository->createQueryBuilder('c')
            ->select('c.id, c.name, c.parentCategoryId, c.sortOrder')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Version signature based on ids and parent relations
        $sigBase = implode(';', array_map(static function ($r) { return (string) ($r['id'] . ':' . ($r['parentCategoryId'] ?? 'null')); }, $all));
        $treeVersion = 'v' . substr(sha1($sigBase), 0, 12);
        $etag = 'W/"' . $treeVersion . '"';

        // Conditional request handling
        $ifNoneMatch = (string) ($request->headers->get('If-None-Match') ?? '');
        if ($ifNoneMatch === $etag) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=300',
            ]);
        }

        $byId = [];
        foreach ($all as $row) {
            $byId[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'label' => (string) ($row['name'] ?? ''),
                'parentId' => $row['parentCategoryId'] !== null ? (int) $row['parentCategoryId'] : null,
                'children' => [],
            ];
        }
        $roots = [];
        foreach ($byId as $cid => $node) {
            $pid = $node['parentId'];
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$byId[$cid];
            } else {
                $roots[] = &$byId[$cid];
            }
        }
        $sortRec = function (&$nodes) use (&$sortRec) {
            usort($nodes, static fn($a, $b) => strcmp((string) $a['label'], (string) $b['label']));
            foreach ($nodes as &$n) if (!empty($n['children'])) $sortRec($n['children']);
        };
        $sortRec($roots);

        $payload = [
            'treeVersion' => $treeVersion,
            'tree' => $roots,
        ];

        $resp = new JsonResponse($payload);
        $resp->setPublic();
        $resp->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resp->headers->set('ETag', $etag);
        $resp->headers->set('Cache-Control', 'public, max-age=300');
        return $resp;
    }
}


