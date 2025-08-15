<?php

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DeliveryType;
use Doctrine\ORM\EntityManagerInterface;

class DeliveryTypeProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Ensures only one DeliveryType has default=true by clearing others when needed,
     * then persists the current entity.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof DeliveryType) {
            if ($data->isDefault()) {
                // Clear default flag from all other rows
                $this->entityManager
                    ->createQuery('UPDATE App\\Entity\\DeliveryType d SET d.default = false WHERE d.default = true')
                    ->execute();
            }

            $this->entityManager->persist($data);
            $this->entityManager->flush();
        }

        return $data;
    }
}


