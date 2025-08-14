<?php
declare(strict_types=1);

namespace App\Controller\Catalog\Category;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\BreadcrumbBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/category', name: 'catalog_category_')]
final class CatalogCategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
        private readonly BreadcrumbBuilder $breadcrumbBuilder,
    ) {}
    

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug, 'visibility' => true]);

        if ($category === null) {
            throw $this->createNotFoundException('Категория не найдена');
        }

        $items = $this->productRepository->findActiveByCategory($category, 20, 0);

        $breadcrumbs = $this->breadcrumbBuilder->buildForCategory($category);

        return $this->render('catalog/category/show.html.twig', [
            'category' => $category,
            'products' => $items,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}


