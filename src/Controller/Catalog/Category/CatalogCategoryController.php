<?php
declare(strict_types=1);

namespace App\Controller\Catalog\Category;

use App\Entity\Category;
use App\Repository\ProductRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/category', name: 'catalog_category_')]
final class CatalogCategoryController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ManagerRegistry $registry): Response
    {
        $categoryRepository = $registry->getRepository(Category::class);
        $categories = $categoryRepository->findBy(['visibility' => true], ['sortOrder' => 'ASC']);

        return $this->render('catalog/category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9\-]+' ], methods: ['GET'])]
    public function show(string $slug, ManagerRegistry $registry, ProductRepository $products): Response
    {
        $categoryRepository = $registry->getRepository(Category::class);
        $category = $categoryRepository->findOneBy(['slug' => $slug, 'visibility' => true]);

        if ($category === null) {
            throw $this->createNotFoundException('Категория не найдена');
        }

        $items = $products->findActiveByCategory($category, 20, 0);

        return $this->render('catalog/category/show.html.twig', [
            'category' => $category,
            'products' => $items,
        ]);
    }
}


