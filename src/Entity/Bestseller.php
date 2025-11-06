<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\BestsellerRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BestsellerRepository::class)]
#[ORM\Table(name: 'bestsellers')]
#[ORM\Index(columns: ['product_id'], name: 'idx_bestseller_product')]
#[ORM\Index(columns: ['sort_order'], name: 'idx_bestseller_sort_order')]
#[ApiResource(
    operations: [new Get(), new GetCollection(), new Post()],
    normalizationContext: ['groups' => ['bestseller:read']],
    denormalizationContext: ['groups' => ['bestseller:write']]
)]
#[UniqueEntity(fields: ['product'], message: 'Товар уже добавлен в хиты продаж')]
class Bestseller
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bestseller:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['bestseller:read', 'bestseller:write'])]
    private Product $product;

    #[ORM\Column(name: 'sort_order', nullable: true, options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    #[Groups(['bestseller:read'])]
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

