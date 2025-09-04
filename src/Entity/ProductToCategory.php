<?php
declare(strict_types=1);

namespace App\Entity;


use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\Repository\ProductToCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints as DoctrineAssert;

#[ORM\Entity(repositoryClass: ProductToCategoryRepository::class)]
#[ORM\Table(
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_product_category',
            columns: ['product_id', 'category_id']
        )
    ],
    indexes: [
        new ORM\Index(name: 'idx_ptc_product', columns: ['product_id']),
        new ORM\Index(name: 'idx_ptc_category', columns: ['category_id'])
    ]
)]
#[ApiResource(
    routePrefix: '/v2',
    operations: [

        new \ApiPlatform\Metadata\Get(normalizationContext: ['groups' => ['product:get']]),
        new \ApiPlatform\Metadata\GetCollection(normalizationContext: ['groups' => ['product:get']]),
        new \ApiPlatform\Metadata\Post(
            denormalizationContext: ['groups' => ['product_category:post']],
            normalizationContext: ['groups' => ['product:get']]
        ),
        new \ApiPlatform\Metadata\Delete()
    ],
    normalizationContext: ['groups' => ['product:get']],
    denormalizationContext: ['groups' => ['product_category:post']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'product' => 'exact',
    'category' => 'exact',
])]
#[DoctrineAssert\UniqueEntity(
    fields: ['product', 'category'],
    message: 'Связка продукт-категория уже существует.'
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
    #[JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Category::class, cascade: ['persist'], inversedBy: 'product')]
    #[Groups(['product:get', 'product_category:post', 'product:post'])]
    #[JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Category $category = null;

    // Helper fields for API input
    #[Groups(['product_category:post'])]
    private ?int $productId = null;

    #[Groups(['product_category:post'])]
    private ?int $categoryId = null;

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

    public function getProductId(): ?int
    {
        return $this->productId ?? $this->product?->getId();
    }

    public function setProductId(?int $productId): self
    {
        $this->productId = $productId;

        return $this;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId ?? $this->category?->getId();
    }

    public function setCategoryId(?int $categoryId): self
    {
        $this->categoryId = $categoryId;

        return $this;
    }

}
