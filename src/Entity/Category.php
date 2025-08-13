<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ["name"], name: "category_name_idx")]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        new Delete()
    ]
)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    private ?bool $visibility = null;

    #[ORM\Column(nullable: true)]
    private ?int $parentCategoryId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaKeywords = null;

    #[ORM\Column(nullable: true)]
    private ?int $sortOrder = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaH1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'navbar_visibility', nullable: true, options: ['default' => true])]
    private ?bool $navbarVisibility = null;

    #[ORM\Column(name: 'footer_visibility', nullable: true, options: ['default' => true])]
    private ?bool $footerVisibility = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ProductToCategory::class, cascade: ['persist', 'remove'])]
    private Collection $product;

    public function __construct()
    {
        $this->product = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getVisibility(): ?bool
    {
        return $this->visibility;
    }

    public function setVisibility(?bool $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getParentCategoryId(): ?int
    {
        return $this->parentCategoryId;
    }

    public function setParentCategoryId(?int $parentCategoryId): self
    {
        $this->parentCategoryId = $parentCategoryId;
        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $metaTitle;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getMetaKeywords(): ?string
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?string $metaKeywords): self
    {
        $this->metaKeywords = $metaKeywords;
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

    public function getMetaH1(): ?string
    {
        return $this->metaH1;
    }

    public function setMetaH1(?string $metaH1): self
    {
        $this->metaH1 = $metaH1;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isNavbarVisibility(): ?bool
    {
        return $this->navbarVisibility;
    }

    public function setNavbarVisibility(?bool $navbarVisibility): self
    {
        $this->navbarVisibility = $navbarVisibility;
        return $this;
    }

    public function isFooterVisibility(): ?bool
    {
        return $this->footerVisibility;
    }

    public function setFooterVisibility(?bool $footerVisibility): self
    {
        $this->footerVisibility = $footerVisibility;
        return $this;
    }

    public function getProduct(): Collection
    {
        return $this->product;
    }

    public function addProduct(ProductToCategory $relation): self
    {
        if (!$this->product->contains($relation)) {
            $this->product->add($relation);
            $relation->setCategory($this);
        }
        return $this;
    }

    public function removeProduct(ProductToCategory $relation): self
    {
        if ($this->product->removeElement($relation)) {
            if ($relation->getCategory() === $this) {
                $relation->setCategory(null);
            }
        }
        return $this;
    }
}


