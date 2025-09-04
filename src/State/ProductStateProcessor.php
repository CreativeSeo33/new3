<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Metadata\Delete as DeleteOperation;
use App\ApiResource\ProductResource;
use App\Entity\Manufacturer;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\PaginationService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Processes ProductResource writes to Product entity.
 */
class ProductStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $repository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly \Symfony\Component\HttpFoundation\RequestStack $requestStack,
        private readonly PaginationService $pagination,
    ) {
    }

    /**
     * @param ProductResource|null $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Handle DELETE explicitly
        if ($operation instanceof DeleteOperation) {
            if (isset($uriVariables['id'])) {
                $entity = $this->repository->find((int) $uriVariables['id']);
                if ($entity instanceof Product) {
                    // Check if product is used in any cart items
                    if ($this->cartItemRepository->isProductUsedInCarts($entity)) {
                        throw new ConflictHttpException(
                            sprintf('Невозможно удалить товар "%s" - он используется в корзине покупателей', $entity->getName())
                        );
                    }

                    $this->em->remove($entity);
                    $this->em->flush();
                }
            }
            // Return null for delete with output: false
            return null;
        }

        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find((int) $uriVariables['id']) ?? new Product();
        } else {
            $entity = new Product();
        }

        // Determine operation type (PATCH vs POST/PUT)
        $isPatch = str_contains(get_class($operation), 'Patch');

        // Parse raw JSON body to know exactly which keys were provided
        $request = $this->requestStack->getCurrentRequest();
        $rawBody = $request?->getContent() ?? '';
        $provided = [];
        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $provided = $decoded;
            }
        }

        // Map incoming DTO -> entity using provided keys only (supports partial updates)
        if (($isPatch && array_key_exists('name', $provided) && $data->name !== null) || (!$isPatch && $data->name !== null)) {
            $entity->setName($data->name);
        }
        if (($isPatch && array_key_exists('slug', $provided)) || !$isPatch) {
            $entity->setSlug($data->slug);
        }
        // Handle pricing (embedded) by replacing embeddable to ensure change tracking
        $hasPrice = array_key_exists('price', $provided) || (!$isPatch && $data->price !== null);
        $hasSalePrice = array_key_exists('salePrice', $provided) || (!$isPatch && $data->salePrice !== null);
        if ($hasPrice || $hasSalePrice) {
            $newPrice = $hasPrice
                ? (array_key_exists('price', $provided) ? (is_numeric($provided['price']) ? (int) $provided['price'] : null) : ($data->price !== null ? (int) $data->price : null))
                : $entity->getPrice();
            $newSalePrice = $hasSalePrice
                ? (array_key_exists('salePrice', $provided) ? (is_numeric($provided['salePrice']) ? (int) $provided['salePrice'] : null) : ($data->salePrice !== null ? (int) $data->salePrice : null))
                : $entity->getSalePrice();
            $entity->setPricingValues($newPrice, $newSalePrice);
            // Duplicate setters to ensure UnitOfWork change detection on embeddables
            $entity->setPrice($newPrice);
            $entity->setSalePrice($newSalePrice);
        }
        if (($isPatch && array_key_exists('status', $provided) && $data->status !== null) || (!$isPatch && $data->status !== null)) {
            $entity->setStatus((bool) $data->status);
        }
        if (($isPatch && array_key_exists('quantity', $provided)) || !$isPatch) {
            $entity->setQuantity($data->quantity !== null ? (int) $data->quantity : null);
        }
        if (($isPatch && array_key_exists('sortOrder', $provided)) || !$isPatch) {
            $entity->setSortOrder($data->sortOrder);
        }
        if (($isPatch && array_key_exists('type', $provided)) || (!$isPatch && $data->type !== null)) {
            $entity->setType($data->type);
        }
        if (($isPatch && array_key_exists('description', $provided)) || !$isPatch) {
            $entity->setDescription($data->description);
        }
        if (($isPatch && array_key_exists('metaTitle', $provided)) || !$isPatch) {
            $entity->setMetaTitle($data->metaTitle);
        }
        if (($isPatch && array_key_exists('metaDescription', $provided)) || !$isPatch) {
            $entity->setMetaDescription($data->metaDescription);
        }
        if (($isPatch && array_key_exists('metaKeywords', $provided)) || !$isPatch) {
            $entity->setMetaKeywords($data->metaKeywords);
        }
        if (($isPatch && array_key_exists('h1', $provided)) || !$isPatch) {
            $entity->setMetaH1($data->h1);
        }
        if (($isPatch && array_key_exists('optionsJson', $provided)) || (!$isPatch && $data->optionsJson !== null)) {
            // optionsJson приходит как массив структур; сохраняем как есть (API-level validation возможна отдельно)
            $entity->setOptionsJson(is_array($data->optionsJson) ? $data->optionsJson : null);
        }
        // Handle optionAssignments
        if (($isPatch && array_key_exists('optionAssignments', $provided)) || (!$isPatch && $data->optionAssignments !== null)) {
            // Очистим существующие назначения и создадим заново по payload
            // В payload ожидаются IRI на option и value
            // Формат элемента: { option: string IRI, value: string IRI|null, height?, bulbsCount?, sku?, price?, lightingArea?, attributes? }
            foreach ($entity->getOptionAssignments() as $existing) {
                $entity->removeOptionAssignment($existing);
                $this->em->remove($existing);
            }
            $normalized = is_array($data->optionAssignments) ? $data->optionAssignments : [];
            foreach ($normalized as $row) {
                if (!is_array($row)) { continue; }
                $optionIri = $row['option'] ?? null;
                $valueIri = $row['value'] ?? null;
                if (!is_string($optionIri) || $optionIri === '') { continue; }
                $optionId = (int) (preg_match('~/(\d+)$~', $optionIri, $m) ? $m[1] : 0);
                $valueId = (int) (is_string($valueIri) && preg_match('~/(\d+)$~', $valueIri, $m2) ? $m2[1] : 0);
                if ($optionId <= 0) { continue; }
                $option = $this->em->getRepository(\App\Entity\Option::class)->find($optionId);
                $value = $valueId > 0 ? $this->em->getRepository(\App\Entity\OptionValue::class)->find($valueId) : null;
                if (!$option || !$value) { continue; }
                $assignment = new \App\Entity\ProductOptionValueAssignment();
                $assignment->setProduct($entity);
                $assignment->setOption($option);
                $assignment->setValue($value);
                $assignment->setHeight(isset($row['height']) && is_numeric($row['height']) ? (int)$row['height'] : null);
                $assignment->setBulbsCount(isset($row['bulbsCount']) && is_numeric($row['bulbsCount']) ? (int)$row['bulbsCount'] : null);
                $assignment->setSku(isset($row['sku']) && is_string($row['sku']) ? $row['sku'] : null);
                $assignment->setOriginalSku(isset($row['originalSku']) && is_string($row['originalSku']) ? $row['originalSku'] : null);
                $assignment->setPrice(isset($row['price']) && is_numeric($row['price']) ? (int)$row['price'] : null);
                $assignment->setSalePrice(isset($row['salePrice']) && is_numeric($row['salePrice']) ? (int)$row['salePrice'] : null);
                $assignment->setSortOrder(isset($row['sortOrder']) && is_numeric($row['sortOrder']) ? (int)$row['sortOrder'] : null);
                $assignment->setQuantity(isset($row['quantity']) && is_numeric($row['quantity']) ? (int)$row['quantity'] : null);
                $assignment->setLightingArea(isset($row['lightingArea']) && is_numeric($row['lightingArea']) ? (int)$row['lightingArea'] : null);
                if (isset($row['attributes']) && is_array($row['attributes'])) { $assignment->setAttributes($row['attributes']); }
                $entity->addOptionAssignment($assignment);
                $this->em->persist($assignment);
            }
        }
        if (($isPatch && array_key_exists('manufacturerId', $provided)) || !$isPatch) {
            $manufacturer = $data->manufacturerId ? $this->em->getRepository(Manufacturer::class)->find((int) $data->manufacturerId) : null;
            $entity->setManufacturerRef($manufacturer);
        }

        $this->em->persist($entity);
        $this->em->flush();

        // Return DTO from fresh entity
        $provider = new ProductStateProvider($this->repository, $this->pagination);
        return $provider->provide($operation, ['id' => $entity->getId()], $context);
    }
}


