<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\ApiResource\ProductResource;
use App\Entity\Product;
use App\Entity\ProductToCategory;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductToCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class ProductBootstrapController extends AbstractController
{
    #[Route('/api/admin/products/{id}/bootstrap', name: 'admin_api_product_bootstrap', methods: ['GET'])]
    public function __invoke(
        int $id,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        ProductToCategoryRepository $ptcRepository,
    ): JsonResponse {
        $product = $productRepository->find($id);
        if (!$product instanceof Product) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Product DTO (reuse ProductStateProvider mapping inline)
        $dto = new ProductResource();
        $dto->id = $product->getId();
        $dto->code = $product->getCode()?->toRfc4122();
        $dto->name = $product->getName();
        $dto->slug = $product->getSlug();
        $dto->price = $product->getPrice();
        $dto->salePrice = $product->getSalePrice();
        $dto->effectivePrice = $product->getEffectivePrice();
        $dto->status = $product->getStatus();
        $dto->quantity = $product->getQuantity();
        $dto->sortOrder = $product->getSortOrder();
        $dto->description = $product->getDescription();
        $dto->metaTitle = $product->getMetaTitle();
        $dto->metaDescription = $product->getMetaDescription();
        $dto->metaKeywords = $product->getMetaKeywords();
        $dto->h1 = $product->getMetaH1();
        $dto->manufacturerId = $product->getManufacturerRef()?->getId();
        $dto->manufacturerName = $product->getManufacturerRef()?->getName();
        $dto->image = [];
        foreach ($product->getImage() as $img) {
            $dto->image[] = [
                'id' => $img->getId(),
                'imageUrl' => $img->getImageUrl(),
                'sortOrder' => $img->getSortOrder(),
            ];
        }

        // Categories (flat list)
        $categories = $categoryRepository->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Product relations
        $rels = $ptcRepository->createQueryBuilder('pc')
            ->leftJoin('pc.category', 'c')
            ->addSelect('c')
            ->where('pc.product = :p')
            ->setParameter('p', $product)
            ->getQuery()
            ->getResult();

        $selectedCategoryIds = [];
        $mainCategoryId = null;
        foreach ($rels as $r) {
            if ($r instanceof ProductToCategory) {
                $cid = $r->getCategory()?->getId();
                if ($cid) $selectedCategoryIds[] = $cid;
                if ($r->getIsParent()) $mainCategoryId = $cid;
            }
        }

        return $this->json([
            'product' => $dto,
            'categories' => $categories,
            'selectedCategoryIds' => array_values(array_unique($selectedCategoryIds)),
            'mainCategoryId' => $mainCategoryId,
        ]);
    }
}




