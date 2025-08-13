<?php
declare(strict_types=1);

namespace App\Controller\Catalog\Product;

use App\Entity\Product;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/product', name: 'catalog_product_')]
final class ProductCatalogController extends AbstractController
{
	#[Route('', name: 'index', methods: ['GET'])]
	public function index(ManagerRegistry $registry): Response
	{
		$repository = $registry->getRepository(Product::class);
		$products = $repository->findBy(['status' => true], ['sortOrder' => 'ASC'], 20, 0);

		return $this->json($products, 200, [], [
			'groups' => ['product:list'],
		]);
	}

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function show(string $slug, ManagerRegistry $registry, Request $request): Response
    {
        $repository = $registry->getRepository(Product::class);
        $product = $repository->findOneBy(['slug' => $slug, 'status' => true]);

        if ($product === null) {
            throw $this->createNotFoundException('Товар не найден');
        }

        $format = $request->getRequestFormat();
        $acceptHeader = $request->headers->get('Accept', '');
        if ($format === 'json' || str_contains($acceptHeader, 'application/json')) {
            return $this->json($product, 200, [], [
                'groups' => ['product:read'],
            ]);
        }

        return $this->render('catalog/product/show.html.twig', [
            'product' => $product,
        ]);
    }
}



