<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['attribute:get']]),
        new Patch(denormalizationContext: ['groups' => ['attribute:patch']]),
        new Post(),
        new Delete(),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['attribute:get']],
    denormalizationContext: ['groups' => ['attribute:post']]
)]
class Attribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['attribute:get', 'attribute_group:get', 'product:get', 'product:post', 'get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['attribute:get', 'attribute:patch', 'attribute:post', 'attribute_group:get', 'product:get'])]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank(groups: ['attribute:post'])]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['attribute:get', 'attribute:patch', 'attribute:post', 'attribute_group:get', 'product:get'])]
    private ?int $sortOrder = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Groups(['attribute:get', 'attribute_group:get'])]
    private ?bool $showInCategory = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['attribute:get', 'attribute_group:get'])]
    private ?string $shortName = null;

    #[ORM\ManyToOne(targetEntity: AttributeGroup::class, inversedBy: 'attributes', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    #[Groups(['attribute:get', 'attribute:patch', 'attribute:post'])]
    private ?AttributeGroup $attributeGroup = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getShowInCategory(): ?bool
    {
        return $this->showInCategory;
    }

    public function setShowInCategory(?bool $showInCategory): self
    {
        $this->showInCategory = $showInCategory;
        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): self
    {
        $this->shortName = $shortName;
        return $this;
    }

    public function getAttributeGroup(): ?AttributeGroup
    {
        return $this->attributeGroup;
    }

    public function setAttributeGroup(?AttributeGroup $attributeGroup): self
    {
        $this->attributeGroup = $attributeGroup;
        return $this;
    }
}


