<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\DeliveryTypeRepository;
use App\Api\Processor\DeliveryTypeProcessor;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new GetCollection(order: ['sortOrder' => 'ASC'], paginationEnabled: false),
        new Get(),
        new Post(processor: DeliveryTypeProcessor::class),
        new Patch(processor: DeliveryTypeProcessor::class),
        new Delete(),
    ]
)]
#[ORM\Entity(repositoryClass: DeliveryTypeRepository::class)]
class DeliveryType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private $code;

    #[ORM\Column(type: 'boolean')]
    private $active = true;

    #[ORM\Column(type: 'integer')]
    private $sortOrder = 0;

    #[ORM\Column(name: 'is_default', type: 'boolean')]
    private $default = false;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isDefault(): ?bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;

        return $this;
    }
}


