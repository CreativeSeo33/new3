<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\ApiResource\ProductResource;
use App\Entity\Category;
use App\Entity\Option;
use App\Entity\OptionValue;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductOptionValueAssignment;
use App\Entity\ProductToCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ProductSyncController extends AbstractController
{
    #[Route('/api/admin/products/{id}/sync', name: 'admin_api_product_sync', methods: ['POST'])]
    public function __invoke(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Product|null $product */
        $product = $em->getRepository(Product::class)->find($id);
        if (!$product) {
            return $this->json(['message' => 'Product not found'], 404);
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];

        $em->wrapInTransaction(function () use ($payload, $product, $em) {
            // 1) Core fields (partial update)
            if (isset($payload['product']) && is_array($payload['product'])) {
                $core = $payload['product'];
                $product->setName($core['name'] ?? $product->getName());
                $product->setSlug($core['slug'] ?? $product->getSlug());
                $product->setPrice(isset($core['price']) ? (is_numeric($core['price']) ? (int) $core['price'] : null) : $product->getPrice());
                $product->setSalePrice(isset($core['salePrice']) ? (is_numeric($core['salePrice']) ? (int) $core['salePrice'] : null) : $product->getSalePrice());
                if (array_key_exists('status', $core)) $product->setStatus((bool) $core['status']);
                $product->setQuantity(isset($core['quantity']) ? (is_numeric($core['quantity']) ? (int) $core['quantity'] : null) : $product->getQuantity());
                $product->setSortOrder(isset($core['sortOrder']) ? (int) $core['sortOrder'] : $product->getSortOrder());
                if (isset($core['type']) && is_string($core['type'])) $product->setType($core['type']);
                $product->setDescription($core['description'] ?? $product->getDescription());
                $product->setMetaTitle($core['metaTitle'] ?? $product->getMetaTitle());
                $product->setMetaDescription($core['metaDescription'] ?? $product->getMetaDescription());
                $product->setMetaH1($core['h1'] ?? $product->getMetaH1());
            }

            // 2) Categories sync
            if (isset($payload['categories']) && is_array($payload['categories'])) {
                $selected = array_values(array_unique(array_filter(array_map(static fn($v) => (int) $v, (array) ($payload['categories']['selectedCategoryIds'] ?? [])), static fn($n) => $n > 0)));
                $mainId = isset($payload['categories']['mainCategoryId']) && is_numeric($payload['categories']['mainCategoryId']) ? (int) $payload['categories']['mainCategoryId'] : null;

                $repo = $em->getRepository(ProductToCategory::class);
                /** @var ProductToCategory[] $current */
                $current = $repo->findBy(['product' => $product]);
                $byCategory = [];
                foreach ($current as $r) {
                    $byCategory[(int) $r->getCategory()?->getId()] = $r;
                }

                // deletes
                foreach ($byCategory as $cid => $rel) {
                    if ($cid && !in_array($cid, $selected, true)) {
                        $em->remove($rel);
                        unset($byCategory[$cid]);
                    }
                }

                // updates
                foreach ($byCategory as $cid => $rel) {
                    $shouldBeParent = ($mainId !== null && $cid === $mainId);
                    if ((bool) $rel->getIsParent() !== $shouldBeParent) {
                        $rel->setIsParent($shouldBeParent);
                    }
                }

                // creates
                if ($selected) {
                    $catRepo = $em->getRepository(Category::class);
                    foreach ($selected as $cid) {
                        if (!isset($byCategory[$cid])) {
                            $cat = $catRepo->find($cid);
                            if ($cat instanceof Category) {
                                $rel = new ProductToCategory();
                                $rel->setProduct($product);
                                $rel->setCategory($cat);
                                $rel->setVisibility(true);
                                $rel->setIsParent($mainId !== null && $cid === $mainId);
                                $em->persist($rel);
                            }
                        }
                    }
                }
            }

            // 3) Option assignments replace
            if (isset($payload['optionAssignments']) && is_array($payload['optionAssignments'])) {
                // remove existing
                foreach ($product->getOptionAssignments() as $existing) {
                    $em->remove($existing);
                }
                $em->flush();
                $optRepo = $em->getRepository(Option::class);
                $valRepo = $em->getRepository(OptionValue::class);
                foreach ((array) $payload['optionAssignments'] as $row) {
                    if (!is_array($row)) continue;
                    $optionIri = (string) ($row['option'] ?? '');
                    $valueIri = (string) ($row['value'] ?? '');
                    if ($optionIri === '' || $valueIri === '') continue;
                    $optId = (int) (preg_match('~/(\d+)$~', $optionIri, $m) ? $m[1] : 0);
                    $valId = (int) (preg_match('~/(\d+)$~', $valueIri, $m) ? $m[1] : 0);
                    if ($optId <= 0 || $valId <= 0) continue;
                    /** @var Option|null $opt */ $opt = $optRepo->find($optId);
                    /** @var OptionValue|null $val */ $val = $valRepo->find($valId);
                    if (!$opt || !$val) continue;
                    $a = new ProductOptionValueAssignment();
                    $a->setProduct($product);
                    $a->setOption($opt);
                    $a->setValue($val);
                    $a->setHeight(isset($row['height']) && is_numeric($row['height']) ? (int) $row['height'] : null);
                    $a->setBulbsCount(isset($row['bulbsCount']) && is_numeric($row['bulbsCount']) ? (int) $row['bulbsCount'] : null);
                    $a->setSku(($row['sku'] ?? null) !== null ? (string) $row['sku'] : null);
                    $a->setOriginalSku(($row['originalSku'] ?? null) !== null ? (string) $row['originalSku'] : null);
                    $a->setPrice(isset($row['price']) && is_numeric($row['price']) ? (int) $row['price'] : null);
                    $a->setSetPrice(array_key_exists('setPrice', $row) ? (bool) $row['setPrice'] : null);
                    $a->setSalePrice(isset($row['salePrice']) && is_numeric($row['salePrice']) ? (int) $row['salePrice'] : null);
                    $a->setLightingArea(isset($row['lightingArea']) && is_numeric($row['lightingArea']) ? (int) $row['lightingArea'] : null);
                    $a->setSortOrder(isset($row['sortOrder']) && is_numeric($row['sortOrder']) ? (int) $row['sortOrder'] : null);
                    $a->setQuantity(isset($row['quantity']) && is_numeric($row['quantity']) ? (int) $row['quantity'] : null);
                    $a->setAttributes(isset($row['attributes']) && is_array($row['attributes']) ? $row['attributes'] : null);
                    $em->persist($a);
                }
            }

            // 4) Photos order
            if (isset($payload['photosOrder']) && is_array($payload['photosOrder'])) {
                $ids = array_values(array_filter(array_map(static fn($v) => (int) $v, (array) $payload['photosOrder']), static fn($n) => $n > 0));
                if ($ids) {
                    /** @var ProductImage[] $images */
                    $images = $em->getRepository(ProductImage::class)->findBy(['product' => $product], ['sortOrder' => 'ASC']);
                    $byId = [];
                    foreach ($images as $img) $byId[(int) $img->getId()] = $img;
                    $sort = 1;
                    foreach ($ids as $pid) {
                        if (isset($byId[$pid])) {
                            $byId[$pid]->setSortOrder($sort++);
                            unset($byId[$pid]);
                        }
                    }
                    foreach ($images as $img) {
                        $iid = (int) $img->getId();
                        if (isset($byId[$iid])) {
                            $byId[$iid]->setSortOrder($sort++);
                            unset($byId[$iid]);
                        }
                    }
                }
            }

            $em->flush();
        });

        // Сбросим 1-го уровня кэш Doctrine, чтобы форвард вернул свежие данные (включая новые вариации)
        $em->clear();

        // Return fresh bootstrap in response to avoid separate GET
        return $this->forward(ProductFormController::class . '::formEdit', ['id' => $product->getId()]);
    }
}


