<?php
declare(strict_types=1);

namespace App\Controller\Catalog;

use App\Service\BestsellerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'catalog_')]
final class IndexController extends AbstractController
{
    public function __construct(
        private BestsellerService $bestsellerService
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $products = $this->bestsellerService->getCachedBestsellers();

        return $this->render('catalog/index.html.twig', [
            'products' => $products,
        ]);
    }
}


