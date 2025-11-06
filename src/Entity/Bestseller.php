<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\BestsellerRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BestsellerRepository::class)]
#[ORM\Table(name: 'bestsellers')]
#[ORM\Index(columns: ['product_id'], name: 'idx_bestseller_product')]
#[ORM\Index(columns: ['sort_order'], name: 'idx_bestseller_sort_order')]
class Bestseller
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Product $product;

    #[ORM\Column(name: 'sort_order', nullable: true, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
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

