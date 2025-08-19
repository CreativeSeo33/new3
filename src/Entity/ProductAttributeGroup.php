<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductAttributeGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['product:get']],
    denormalizationContext: ['groups' => ['product_attribute_group:post']]
)]
#[ORM\Entity(repositoryClass: ProductAttributeGroupRepository::class)]
class ProductAttributeGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product:get', 'product:post'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productAttributeGroups')]
    #[Groups(['product_attribute_group:post', 'product:post'])]
    private ?Product $product = null;

    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'productAttributeGroup', cascade: ['persist'])]
    #[Groups(['product:get', 'product:post', 'product:post'])]
    private Collection $attribute;

    #[ORM\ManyToOne(targetEntity: AttributeGroup::class, inversedBy: 'productAttributeGroups', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'RESTRICT', nullable: true)]
    #[Groups(['product:get', 'product_attribute_group:post', 'product:post'])]
    private ?AttributeGroup $attributeGroup = null;

    public function __construct()
    {
        $this->attribute = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return Collection|ProductAttribute[]
     */
    public function getAttribute(): Collection
    {
        return $this->attribute;
    }

    public function addAttribute(ProductAttribute $attribute): self
    {
        if (!$this->attribute->contains($attribute)) {
            $this->attribute[] = $attribute;
            $attribute->setProductAttributeGroup($this);
        }

        return $this;
    }

    public function removeAttribute(ProductAttribute $attribute): self
    {
        if ($this->attribute->contains($attribute)) {
            $this->attribute->removeElement($attribute);
            // set the owning side to null (unless already changed)
            if ($attribute->getProductAttributeGroup() === $this) {
                $attribute->setProductAttributeGroup(null);
            }
        }

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
