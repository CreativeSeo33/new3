<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/settings',
            normalizationContext: ['groups' => ['settings:admin:get']],
            paginationEnabled: false
        ),
        new Get(
            uriTemplate: '/admin/settings/{id}',
            normalizationContext: ['groups' => ['settings:admin:get']]
        ),
        new Post(
            uriTemplate: '/admin/settings',
            denormalizationContext: ['groups' => ['settings:admin:write']]
        ),
        new Put(
            uriTemplate: '/admin/settings/{id}',
            denormalizationContext: ['groups' => ['settings:admin:write']]
        ),
        new Patch(
            uriTemplate: '/admin/settings/{id}',
            denormalizationContext: ['groups' => ['settings:admin:write']]
        ),
        new Delete(
            uriTemplate: '/admin/settings/{id}'
        ),
    ]
)]
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['settings:admin:get'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['settings:admin:get', 'settings:admin:write'])]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['settings:admin:get', 'settings:admin:write'])]
    private $value;

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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }
}
