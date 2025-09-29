<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\Search\ProductIndexer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SearchController extends AbstractController
{
    public function __construct(private readonly ProductIndexer $indexer) {}

    #[Route('/api/admin/search/reindex-products', name: 'admin_search_reindex_products', methods: ['POST'])]
    public function reindexProducts(): Response
    {
        $start = microtime(true);
        $count = $this->indexer->reindexAll();
        $seconds = microtime(true) - $start;

        return new JsonResponse([
            'status' => 'accepted',
            'count' => $count,
            'seconds' => round($seconds, 2),
        ], 202);
    }
}



