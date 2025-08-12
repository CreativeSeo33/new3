<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
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
    ) {
    }

    /**
     * @param ProductResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        if (isset($uriVariables['id'])) {
            $entity = $this->repository->find((int) $uriVariables['id']) ?? new Product();
        } else {
            $entity = new Product();
        }

        // Map incoming DTO -> entity (partial for patch)
        if (array_key_exists('name', get_object_vars($data)) && $data->name !== null) {
            $entity->setName($data->name);
        }
        if (array_key_exists('slug', get_object_vars($data))) {
            $entity->setSlug($data->slug);
        }
        if (array_key_exists('price', get_object_vars($data))) {
            $entity->setPrice($data->price);
        }
        if (array_key_exists('salePrice', get_object_vars($data))) {
            $entity->setSalePrice($data->salePrice);
        }
        if (array_key_exists('status', get_object_vars($data)) && $data->status !== null) {
            $entity->setStatus((bool) $data->status);
        }
        if (array_key_exists('quantity', get_object_vars($data))) {
            $entity->setQuantity($data->quantity);
        }
        if (array_key_exists('description', get_object_vars($data))) {
            $entity->setDescription($data->description);
        }
        if (array_key_exists('metaTitle', get_object_vars($data))) {
            $entity->setMetaTitle($data->metaTitle);
        }
        if (array_key_exists('metaDescription', get_object_vars($data))) {
            $entity->setMetaDescription($data->metaDescription);
        }
        if (array_key_exists('metaKeywords', get_object_vars($data))) {
            $entity->setMetaKeywords($data->metaKeywords);
        }
        if (array_key_exists('h1', get_object_vars($data))) {
            $entity->setMetaH1($data->h1);
        }
        if (array_key_exists('manufacturerId', get_object_vars($data))) {
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


