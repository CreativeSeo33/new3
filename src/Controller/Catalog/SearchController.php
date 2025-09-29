<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Repository\ProductRepository;
use App\Service\Search\ProductSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductSearch $productSearch,
    ) {}

    #[Route(path: '/search/', name: 'catalog_search', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $text = trim((string)$request->query->get('text', ''));

        $products = [];
        $total = 0;
        if ($text !== '') {
            $limit = 40;
            $offset = 0;
            $found = $this->productSearch->search($text, $limit, $offset);
            $ids = $found['ids'] ?? [];
            $total = (int)($found['total'] ?? 0);

            if (!empty($ids)) {
                $entities = $this->productRepository->createQueryBuilder('p')
                    ->leftJoin('p.image', 'img')->addSelect('img')
                    ->andWhere('p.id IN (:ids)')
                    ->andWhere('p.status = true')
                    ->setParameter('ids', $ids)
                    ->getQuery()->getResult();

                $byId = [];
                foreach ($entities as $e) { $byId[$e->getId()] = $e; }
                foreach ($ids as $id) { if (isset($byId[$id])) { $products[] = $byId[$id]; } }
            }
        }

        return $this->render('catalog/search/index.html.twig', [
            'text' => $text,
            'total' => $total,
            'products' => $products,
        ]);
    }
}


