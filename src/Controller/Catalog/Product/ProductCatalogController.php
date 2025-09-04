<?php
declare(strict_types=1);

namespace App\Controller\Catalog\Product;

use App\Entity\Product;
use App\Service\BreadcrumbBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/product', name: 'catalog_product_')]
final class ProductCatalogController extends AbstractController
{
    public function __construct(private readonly BreadcrumbBuilder $breadcrumbBuilder) {}
    

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function show(string $slug, ManagerRegistry $registry, Request $request): Response
    {
        /** @var \App\Repository\ProductRepository $repository */
        $repository = $registry->getRepository(Product::class);
        $product = $repository->findOneActiveWithAttributesBySlug($slug);
        

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

        

        $breadcrumbs = $this->breadcrumbBuilder->buildForProduct($product);

        return $this->render('catalog/product/show.html.twig', [
            'product' => $product,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}



