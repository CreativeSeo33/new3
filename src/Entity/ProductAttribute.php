<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductAttributeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;

#[ApiResource(
    operations: [
        new Get(),
        new Patch(
            denormalizationContext: ['groups' => ['product_attribute:patch']]
        ),
        new Delete()
    ],
    denormalizationContext: ['groups' => ['product_attribute:post']],
)]
#[ORM\Entity(repositoryClass: ProductAttributeRepository::class)]
class ProductAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product:get', 'product:post'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['product:get', 'product_attribute:post', 'product_attribute:patch', 'product:post'])]
    #[Assert\Length(max: 255)]
    private ?string $text = null;

    #[ORM\ManyToOne(targetEntity: ProductAttributeGroup::class, inversedBy: 'attribute', cascade: ['persist'])]
    #[Groups(['product_attribute:post', 'product:post'])]
    private ?ProductAttributeGroup $productAttributeGroup = null;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[Groups(['product_attribute:post', 'product:get', 'product:post'])]
    private ?Attribute $attribute = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getProductAttributeGroup(): ?ProductAttributeGroup
    {
        return $this->productAttributeGroup;
    }

    public function setProductAttributeGroup(?ProductAttributeGroup $productAttributeGroup): self
    {
        $this->productAttributeGroup = $productAttributeGroup;

        return $this;
    }

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): self
    {
        $this->attribute = $attribute;

        return $this;
    }

}
