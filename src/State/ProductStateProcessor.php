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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Processes ProductResource writes to Product entity.
 */
class ProductStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $repository,
        private readonly \Symfony\Component\HttpFoundation\RequestStack $requestStack,
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
        if (($isPatch && array_key_exists('manufacturerId', $provided)) || !$isPatch) {
            $manufacturer = $data->manufacturerId ? $this->em->getRepository(Manufacturer::class)->find((int) $data->manufacturerId) : null;
            $entity->setManufacturerRef($manufacturer);
        }

        $this->em->persist($entity);
        $this->em->flush();

        // Return DTO from fresh entity
        $provider = new ProductStateProvider($this->repository);
        return $provider->provide($operation, ['id' => $entity->getId()], $context);
    }
}


