<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductSeo;
use App\Entity\ProductToCategory;
use App\Entity\ProductImage;
use App\Entity\ProductOptionValueAssignment;
use App\Entity\ProductAttributeAssignment;
use App\Entity\Carousel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProductCopyService
{
    /**
     * AI-META v1
     * role: Копирование товара вместе со связанными сущностями; опциональная смена типа
     * module: Admin
     * dependsOn:
     *   - Doctrine\ORM\EntityManagerInterface
     *   - Symfony\Component\String\Slugger\SluggerInterface
     *   - App\Service\ProductLifecycleService
     * invariants:
     *   - Копирование выполняется в ручной транзакции (begin/commit/rollback)
     *   - Slug генерируется уникальным; опции/SEO/категории/атрибуты копируются по флагам
     * transaction: custom
     * tests:
     *   - tests/Service/ProductClonerTest.php
     * lastUpdated: 2025-09-15
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ProductLifecycleService $lifecycleService,
    ) {}

    public function copyProduct(int $productId, array $options = []): Product
    {
        $defaultOptions = [
            'copyCategories' => true,
            'copyImages' => true,
            'copyAttributes' => true,
            'copyOptions' => null, // null = автоматически по типу товара
            'copySeo' => true,
            'namePrefix' => 'Копия ',
            'setInactive' => true,
            'changeType' => null // Возможность изменить тип при копировании
        ];

        $options = array_merge($defaultOptions, $options);

        // Найти оригинальный товар
        $originalProduct = $this->entityManager->find(Product::class, $productId);
        if (!$originalProduct) {
            throw new \InvalidArgumentException('Товар не найден (ID: ' . $productId . ')');
        }

        // Использовать транзакцию для атомарности
        $this->entityManager->beginTransaction();

        try {
            $newProduct = $this->createProductCopy($originalProduct, $options);

            // Опции копируем в зависимости от типа товара
            $shouldCopyOptions = $this->shouldCopyOptions($originalProduct, $options);
            if ($shouldCopyOptions) {
                $this->copyOptionAssignments($originalProduct, $newProduct);
            }

            // Копировать связанные данные
            if ($options['copyCategories']) {
                $this->copyCategories($originalProduct, $newProduct);
            }

            if ($options['copyImages']) {
                $this->copyImages($originalProduct, $newProduct);
            }

            if ($options['copyAttributes']) {
                $this->copyAttributes($originalProduct, $newProduct);
            }

            if ($options['copySeo']) {
                $this->copySeo($originalProduct, $newProduct);
            }

            // Генерировать уникальные значения через lifecycle service
            $this->lifecycleService->handlePrePersist($newProduct);

            // Сохранить новый товар
            $this->entityManager->persist($newProduct);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $newProduct;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    public function copyProductWithTypeChange(int $productId, string $newType, array $additionalOptions = []): Product
    {
        $options = array_merge($additionalOptions, [
            'changeType' => $newType
        ]);

        return $this->copyProduct($productId, $options);
    }

    private function shouldCopyOptions(Product $original, array $options): bool
    {
        // Если явно указано в опциях
        if ($options['copyOptions'] !== null) {
            return $options['copyOptions'];
        }

        // Для вариативных товаров (оба подтипа) опции ОБЯЗАТЕЛЬНЫ для копирования
        // Если не копировать опции, то товар станет simple
        return $original->isVariable() || $original->isVariableNoPrices();
    }

    private function createProductCopy(Product $original, array $options): Product
    {
        $newProduct = new Product();

        // Копировать основные поля
        $newName = $options['namePrefix'] . $original->getName();
        $newProduct->setName($newName);

        // Генерировать уникальный slug
        $baseSlug = $this->slugifyName($newName);
        $newProduct->setSlug($this->generateUniqueSlug($baseSlug));
        $newProduct->setDescription($original->getDescription());
        $newProduct->setSortOrder($original->getSortOrder());
        $newProduct->setManufacturerRef($original->getManufacturerRef());
        $newProduct->setOptionsJson($original->getOptionsJson());
        $newProduct->setAttributeJson($original->getAttributeJson());

        // Определяем, нужно ли копировать опции
        $shouldCopyOptions = $this->shouldCopyOptions($original, $options);

        // Установить тип товара
        $newType = $options['changeType'] ?? $original->getType();

        // Если оригинальный товар variable, но мы не копируем опции, то делаем его simple
        if (($original->isVariable() || $original->isVariableNoPrices()) && !$shouldCopyOptions) {
            $newType = Product::TYPE_SIMPLE;
        }

        $newProduct->setType($newType);

        // Установить цены в зависимости от типа
        $this->setCopyPricing($original, $newProduct, $newType);

        // Установить статус
        $newProduct->setStatus($options['setInactive'] ? false : $original->getStatus());

        // Скопировать quantity только для simple товаров
        if ($newProduct->isSimple()) {
            $newProduct->setQuantity($original->getQuantity());
        }

        return $newProduct;
    }

    private function setCopyPricing(Product $original, Product $newProduct, string $newType): void
    {
        if ($newType === Product::TYPE_VARIABLE) {
            // Для variable товаров цены будут обнулены в lifecycle service
            $newProduct->setPrice(null);
            $newProduct->setSalePrice(null);
        } elseif ($newType === Product::TYPE_VARIABLE_NO_PRICES) {
            // Для вариативного без цен: используем цены с уровня товара (как есть)
            $newProduct->setPrice($original->getPrice());
            $newProduct->setSalePrice($original->getSalePrice());
        } elseif ($newType === Product::TYPE_SIMPLE && $original->isVariable()) {
            // Variable → Simple: берем минимальную цену из вариаций
            $minPrice = $this->getMinPriceFromOptions($original);
            $newProduct->setPrice($minPrice);
            $newProduct->setSalePrice(null);
        } else {
            // Simple → Simple: копируем основные цены
            $newProduct->setPrice($original->getPrice());
            $newProduct->setSalePrice($original->getSalePrice());
        }
    }

    private function copyCategories(Product $original, Product $newProduct): void
    {
        foreach ($original->getCategory() as $categoryRelation) {
            $newCategoryRelation = new ProductToCategory();
            $newCategoryRelation->setProduct($newProduct);
            $newCategoryRelation->setCategory($categoryRelation->getCategory());
            $newCategoryRelation->setIsParent($categoryRelation->getIsParent());

            // Обработка nullable полей
            if ($categoryRelation->getPosition() !== null) {
                $newCategoryRelation->setPosition($categoryRelation->getPosition());
            }
            if ($categoryRelation->getVisibility() !== null) {
                $newCategoryRelation->setVisibility($categoryRelation->getVisibility());
            }

            $this->entityManager->persist($newCategoryRelation);
            $newProduct->addCategory($newCategoryRelation);
        }
    }

    private function copyImages(Product $original, Product $newProduct): void
    {
        foreach ($original->getImage() as $image) {
            $newImage = new ProductImage();
            $newImage->setProduct($newProduct);
            $newImage->setImageUrl($image->getImageUrl());
            $newImage->setSortOrder($image->getSortOrder());

            $this->entityManager->persist($newImage);
            $newProduct->addImage($newImage);
        }
    }

    private function copyAttributes(Product $original, Product $newProduct): void
    {
        foreach ($original->getAttributeAssignments() as $attribute) {
            $newAttribute = new ProductAttributeAssignment();
            $newAttribute->setProduct($newProduct);
            $newAttribute->setAttribute($attribute->getAttribute());
            $newAttribute->setAttributeGroup($attribute->getAttributeGroup());
            $newAttribute->setDataType($attribute->getDataType());
            $newAttribute->setStringValue($attribute->getStringValue());
            $newAttribute->setTextValue($attribute->getTextValue());
            $newAttribute->setIntValue($attribute->getIntValue());
            $newAttribute->setDecimalValue($attribute->getDecimalValue());
            $newAttribute->setBoolValue($attribute->getBoolValue());
            $newAttribute->setDateValue($attribute->getDateValue());
            $newAttribute->setJsonValue($attribute->getJsonValue());
            $newAttribute->setUnit($attribute->getUnit());
            $newAttribute->setPosition($attribute->getPosition());

            $this->entityManager->persist($newAttribute);
            $newProduct->addAttributeAssignment($newAttribute);
        }
    }

    private function copyOptionAssignments(Product $original, Product $newProduct): void
    {
        foreach ($original->getOptionAssignments() as $assignment) {
            $newAssignment = new ProductOptionValueAssignment();
            $newAssignment->setProduct($newProduct);
            $newAssignment->setOption($assignment->getOption());
            $newAssignment->setValue($assignment->getValue());
            $newAssignment->setPrice($assignment->getPrice());
            $newAssignment->setSalePrice($assignment->getSalePrice());
            $newAssignment->setQuantity($assignment->getQuantity());
            $newAssignment->setSku($this->generateNewSku($assignment->getSku()));
            $newAssignment->setHeight($assignment->getHeight());
            $newAssignment->setLightingArea($assignment->getLightingArea());
            $newAssignment->setBulbsCount($assignment->getBulbsCount());
            $newAssignment->setAttributes($assignment->getAttributes());
            $newAssignment->setSortOrder($assignment->getSortOrder());

            $this->entityManager->persist($newAssignment);
            $newProduct->addOptionAssignment($newAssignment);
        }
    }

    private function copySeo(Product $original, Product $newProduct): void
    {
        $originalSeo = $original->getSeo();
        if ($originalSeo) {
            $newSeo = new ProductSeo($newProduct);
            $newSeo->setMetaTitle($originalSeo->getMetaTitle());
            $newSeo->setMetaDescription($originalSeo->getMetaDescription());
            $newSeo->setMetaKeywords($originalSeo->getMetaKeywords());
            $newSeo->setH1($originalSeo->getH1());

            $this->entityManager->persist($newSeo);
        }
    }

    private function generateNewSku(?string $originalSku): ?string
    {
        if (!$originalSku) {
            return null;
        }

        // Добавляем суффикс "_copy" к оригинальному SKU
        return $originalSku . '_copy';
    }

    private function getMinPriceFromOptions(Product $product): ?int
    {
        $minPrice = null;
        foreach ($product->getOptionAssignments() as $assignment) {
            $currentPrice = $assignment->getSalePrice() ?? $assignment->getPrice();
            if ($currentPrice !== null && ($minPrice === null || $currentPrice < $minPrice)) {
                $minPrice = $currentPrice;
            }
        }
        return $minPrice;
    }

    private function slugifyName(string $name): string
    {
        // Конвертируем кириллицу в латиницу
        $transliteration = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
        ];

        $slug = strtr($name, $transliteration);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        // Если slug пустой, возвращаем fallback
        return $slug ?: 'product';
    }

    private function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;

        // Проверяем уникальность slug
        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $existingProduct = $this->entityManager->getRepository(Product::class)->findOneBy(['slug' => $slug]);
        return $existingProduct !== null;
    }
}
