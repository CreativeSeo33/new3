<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Repository\ProductRepository;
use App\Service\Search\ProductSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route(path: '/search/products', name: 'catalog_search_products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        $text = trim((string)$request->query->get('text', ''));
        $raw = $request->query->all('f');

        $products = [];
        if ($text !== '') {
            $found = $this->productSearch->search($text, 5000, 0);
            $ids = $found['ids'] ?? [];
            if (!empty($ids)) {
                // Apply facet filters (OR within code, AND between codes)
                $qb = $this->productRepository->createQueryBuilder('p')
                    ->leftJoin('p.image', 'img')->addSelect('img')
                    ->andWhere('p.status = true')
                    ->andWhere('p.id IN (:ids)')->setParameter('ids', $ids);

                $i = 0;
                foreach ($raw as $code => $csv) {
                    $values = array_values(array_filter(array_map('trim', explode(',', (string)$csv)), static fn($v) => $v !== ''));
                    if (empty($values)) { continue; }
                    $i++;
                    $codeParam = 'f_code_' . $i;
                    $valsParam = 'f_vals_' . $i;
                    $existsAttr = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductAttributeAssignment paa' . $i . ' JOIN paa' . $i . '.attribute a' . $i . ' WHERE paa' . $i . '.product = p AND a' . $i . '.code = :' . $codeParam . ' AND paa' . $i . '.stringValue IN (:' . $valsParam . '))';
                    $existsOpt = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pova' . $i . ' JOIN pova' . $i . '.option o' . $i . ' JOIN pova' . $i . '.value ov' . $i . ' WHERE pova' . $i . '.product = p AND o' . $i . '.code = :' . $codeParam . ' AND ov' . $i . '.value IN (:' . $valsParam . '))';
                    $qb->andWhere('(' . $existsAttr . ' OR ' . $existsOpt . ')')
                        ->setParameter($codeParam, $code)
                        ->setParameter($valsParam, $values);
                }

                $entities = $qb->getQuery()->getResult();
                $byId = [];
                foreach ($entities as $e) { $byId[$e->getId()] = $e; }
                foreach ($ids as $id) { if (isset($byId[$id])) { $products[] = $byId[$id]; } }
            }
        }

        return $this->render('catalog/category/_grid.html.twig', [ 'products' => $products ]);
    }
}


