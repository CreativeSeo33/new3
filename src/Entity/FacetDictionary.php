<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FacetDictionaryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacetDictionaryRepository::class)]
#[ORM\Table(name: 'facet_dictionary')]
#[ORM\UniqueConstraint(name: 'UNIQ_FD_CATEGORY', columns: ['category_id'])]
#[ORM\Index(name: 'IDX_FD_CATEGORY', columns: ['category_id'])]
class FacetDictionary
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Category $category = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attributesJson = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $optionsJson = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $priceMin = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $priceMax = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getAttributesJson(): ?array
    {
        return $this->attributesJson;
    }

    public function setAttributesJson(?array $attributesJson): static
    {
        $this->attributesJson = $attributesJson;
        return $this;
    }

    public function getOptionsJson(): ?array
    {
        return $this->optionsJson;
    }

    public function setOptionsJson(?array $optionsJson): static
    {
        $this->optionsJson = $optionsJson;
        return $this;
    }

    public function getPriceMin(): ?int
    {
        return $this->priceMin;
    }

    public function setPriceMin(?int $priceMin): static
    {
        $this->priceMin = $priceMin;
        return $this;
    }

    public function getPriceMax(): ?int
    {
        return $this->priceMax;
    }

    public function setPriceMax(?int $priceMax): static
    {
        $this->priceMax = $priceMax;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function updateTimestamp(): static
    {
        $this->updatedAt = new \DateTime();
        return $this;
    }
}
