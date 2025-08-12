<?php
declare(strict_types=1);

namespace App\Entity;


use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductToCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductToCategoryRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['product:get']],
    denormalizationContext: ['groups' => ['product_category:post']]
)]
class ProductToCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:get'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true, options: ['default' => false])]
    #[Groups(['product:get', 'product_category:post', 'product:post'])]
    private ?bool $isParent = null;

    #[ORM\Column(nullable: true, options: ['default' => 1])]
    #[Groups(['product:get', 'product_category:post', 'product:post'])]
    #[Assert\PositiveOrZero]
    private ?int $position = null;

    #[ORM\Column(nullable: true, options: ['default' => true])]
    #[Groups(['product:get', 'product_category:post', 'product:post'])]
    private ?bool $visibility = null;

    #[ORM\ManyToOne(targetEntity: Product::class, cascade: ['persist'], inversedBy: 'category')]
    #[Groups(['product_category:post', 'product:post', 'product_category:delete'])]
    #[JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Category::class, cascade: ['persist'], inversedBy: 'product')]
    #[Groups(['product:get', 'product_category:post', 'product:post'])]
    private ?Category $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsParent(): ?bool
    {
        return $this->isParent;
    }

    public function setIsParent(bool $isParent): self
    {
        $this->isParent = $isParent;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getVisibility(): ?bool
    {
        return $this->visibility;
    }

    public function setVisibility(bool $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

        return $this;
    }

}
