<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\AttributeGroupRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping\OrderBy;
use App\Api\Processor\DeleteAttributeGroupRestrictProcessor;

#[ORM\Entity(repositoryClass: AttributeGroupRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['attribute_group:get']]),
        new Patch(denormalizationContext: ['groups' => ['attribute_group:patch']]),
        new Post(denormalizationContext: ['groups' => ['attribute_group:post']]),
        new Delete(processor: DeleteAttributeGroupRestrictProcessor::class),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['attribute_group:get']],
    denormalizationContext: ['groups' => ['attribute_group:post']]
)]
class AttributeGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['attribute_group:get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['attribute_group:get', 'attribute_group:post', 'attribute_group:patch'])]
    private ?string $name = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['attribute_group:get', 'attribute_group:post', 'attribute_group:patch'])]
    private ?int $sortOrder = null;

    #[ORM\Column(type: 'string', length: 100, unique: true, nullable: true)]
    #[Groups(['attribute_group:get','attribute_group:post','attribute_group:patch'])]
    private ?string $code = null;

    #[ORM\OneToMany(mappedBy: 'attributeGroup', targetEntity: Attribute::class, cascade: ['persist'], orphanRemoval: false)]
    #[Groups(['attribute_group:get'])]
    #[OrderBy(['sortOrder' => 'ASC'])]
    private Collection $attributes;

    

    public function __construct()
    {
        $this->attributes = new ArrayCollection();
        
    }

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

    

    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(Attribute $attribute): self
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setAttributeGroup($this);
        }
        return $this;
    }

    public function removeAttribute(Attribute $attribute): self
    {
        if ($this->attributes->removeElement($attribute)) {
            if ($attribute->getAttributeGroup() === $this) {
                $attribute->setAttributeGroup(null);
            }
        }
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code ? strtolower($code) : null;
        return $this;
    }
}


 