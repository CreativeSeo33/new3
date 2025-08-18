<?php

namespace App\Entity;

use App\Repository\OrderStatusRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        // Основные операции
        new Get(normalizationContext: ['groups' => ['orderStatus:admin:get']]),
        new GetCollection(
            normalizationContext: ['groups' => ['orderStatus:admin:get']],
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true
        ),
        new Post(
            denormalizationContext: ['groups' => ['orderStatus:post']]
        ),
        new Put(
            denormalizationContext: ['groups' => ['orderStatus:post']]
        ),
        new Patch(
            denormalizationContext: ['groups' => ['orderStatus:patch']]
        ),
        new Delete(),

        // Admin-роуты с prefix /admin для совместимости с фронтом
        new GetCollection(
            uriTemplate: '/admin/order-statuses',
            normalizationContext: ['groups' => ['orderStatus:admin:get']],
            paginationEnabled: false
        ),
        new Get(uriTemplate: '/admin/order-statuses/{id}', normalizationContext: ['groups' => ['orderStatus:admin:get']]),
        new Post(uriTemplate: '/admin/order-statuses', denormalizationContext: ['groups' => ['orderStatus:post']]),
        new Put(uriTemplate: '/admin/order-statuses/{id}', denormalizationContext: ['groups' => ['orderStatus:post']]),
        new Patch(uriTemplate: '/admin/order-statuses/{id}', denormalizationContext: ['groups' => ['orderStatus:patch']]),
        new Delete(uriTemplate: '/admin/order-statuses/{id}')
    ],
    order: ['sort' => 'ASC', 'name' => 'ASC']
)]
#[ORM\Entity(repositoryClass: OrderStatusRepository::class)]
class OrderStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['orderStatus:admin:get'])]
    private $id;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['orderStatus:admin:get', 'orderStatus:post', 'orderStatus:patch'])]
    private $name;

    #[ORM\Column(type: 'integer')]
    #[Groups(['orderStatus:admin:get', 'orderStatus:post', 'orderStatus:patch'])]
    private $sort;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }
}
