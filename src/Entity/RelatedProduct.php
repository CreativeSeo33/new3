<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\RelatedProductRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping\Index;

#[ORM\Entity(repositoryClass: RelatedProductRepository::class)]
#[ORM\Table(name: 'related_products')]
#[Index(columns: ['product_id'], name: 'idx_related_product_product')]
#[Index(columns: ['related_product_id'], name: 'idx_related_product_related')]
#[Index(columns: ['sort_order'], name: 'idx_related_product_sort_order')]
#[ApiResource(
    operations: [new Get(), new GetCollection(), new Post(), new Patch(), new Delete()],
    normalizationContext: ['groups' => ['related_product:read']],
    denormalizationContext: ['groups' => ['related_product:write']]
)]
#[UniqueEntity(
    fields: ['product', 'relatedProduct'],
    message: 'Эта пара товар / похожий товар уже добавлена'
)]
class RelatedProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['related_product:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'relatedProducts')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['related_product:read', 'related_product:write'])]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'related_product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['related_product:read', 'related_product:write'])]
    private Product $relatedProduct;

    #[ORM\Column(name: 'sort_order', nullable: true, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['related_product:read', 'related_product:write'])]
    private ?int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getRelatedProduct(): Product
    {
        return $this->relatedProduct;
    }

    public function setRelatedProduct(Product $relatedProduct): self
    {
        $this->relatedProduct = $relatedProduct;

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
}


