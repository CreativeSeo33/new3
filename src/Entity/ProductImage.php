<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
/** не хватает attributes={"order"={"sortOrder": "ASC"}} */
#[ORM\Table]
#[ORM\Index(name: 'product_id', columns: ['product_id'])]
#[ORM\Index(name: 'product_image_sort_idx', columns: ['sort_order'])]
#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product:get'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product:get', 'product:post', 'order:get'])]
    #[Assert\Length(max: 255)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['product:get', 'product:post'])]
    private ?int $sortOrder = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'image')]
    #[JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Product $product = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }
}
