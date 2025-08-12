<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Index(columns: ["name"], name: 'name')]
#[ORM\Index(columns: ["status"], name: 'product_status_idx')]
#[ORM\Index(columns: ["manufacturer"], name: 'product_manufacturer_idx')]
#[ORM\Index(columns: ["date_added"], name: 'product_date_added_idx')]
#[ORM\Index(columns: ["sort_order"], name: 'product_sort_order_idx')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'Product code must be unique')]
#[UniqueEntity(fields: ['slug'], message: 'Slug must be unique')]
#[Assert\Expression(
    expression: 'this.getSalePrice() === null or this.getPrice() === null or this.getSalePrice() <= this.getPrice()',
    message: 'Sale price must be less than or equal to price'
)]
#[ApiResource(
    operations: [
        new Get(),
        new Post(
            denormalizationContext: ['groups' => ['product:post']]
        ),
        new Delete(),
        new Patch(
            denormalizationContext: ['groups' => ['product:patch']]
        ),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['product:get']]
)]
#[ApiFilter(OrderFilter::class,
    properties: ['dateAdded','status','manufacturer','sortOrder'],
    arguments: ['orderParameterName' => 'order']
)]
#[ApiFilter(SearchFilter::class,
    properties: ['name' => 'partial','manufacturer' => 'exact']
)]
#[ApiFilter(BooleanFilter::class,
    properties: ['status' => 'exact']
)]
class Product
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:get', 'product_option:post'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\NotBlank(groups: ['product:post'])]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', message: 'Slug format is invalid')]
    private ?string $slug = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\PositiveOrZero]
    private ?int $price = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\PositiveOrZero]
    private ?int $salePrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    private ?int $sortOrder = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\Length(max: 255)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\Length(max: 255)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\Length(max: 255)]
    private ?string $metaKeywords = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    private ?int $manufacturer = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    private ?bool $status = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\PositiveOrZero]
    private ?int $quantity = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    #[Assert\Length(max: 255)]
    private ?string $metaH1 = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product:post'])]
    private ?\DateTimeInterface $dateAdded = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_edited = null;


    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttributeGroup::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:get', 'product:post'])]
    private Collection $productAttributeGroups;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductToCategory::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:get', 'product:post'])]
    #[ApiFilter(SearchFilter::class,
        properties: ['category.category' => 'exact']
    )]
    private Collection $category;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:get', 'product:post', 'order:get'])]
    #[OrderBy(['sortOrder' => 'ASC'])]
    private Collection $image;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product_option:post', 'product:post'])]
    private ?array $optionsJson = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:get', 'product:patch', 'product_option:post', 'product:post'])]
    private ?array $attributeJson = [];

    #[Assert\Ulid]
    #[ORM\Column(type: UlidType::NAME, unique: true, nullable: true)]
    #[Groups(['product:get'])]
    private ?Ulid $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:get', 'product:patch'])]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Carousel::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:get', 'product:post', 'order:get'])]
    #[OrderBy(['sort' => 'ASC'])]
    private Collection $carousels;

    public function __construct()
    {
        $this->productAttributeGroups = new ArrayCollection();
        $this->category = new ArrayCollection();
        $this->image = new ArrayCollection();
        $this->carousels = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getSalePrice(): ?int
    {
        return $this->salePrice;
    }

    public function setSalePrice(?int $salePrice): self
    {
        $this->salePrice = $salePrice;

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

    public function getManufacturer(): ?int
    {
        return $this->manufacturer;
    }

    public function setManufacturer(?int $manufacturer): self
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

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

    public function getDateAdded(): ?\DateTimeInterface
    {
        return $this->dateAdded;
    }

    #[ORM\PrePersist]
    public function setDateAdded(): void
    {
        if ($this->dateAdded === null) {
            $this->dateAdded = new \DateTime();
        }
    }

    public function getDateEdited(): ?\DateTimeInterface
    {
        return $this->date_edited;
    }

    public function setDateEdited(?\DateTimeInterface $date_edited): self
    {
        $this->date_edited = $date_edited;

        return $this;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->date_edited = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function ensureSlug(): void
    {
        if (($this->slug === null || $this->slug === '') && $this->name) {
            $this->slug = $this->slugifyName($this->name);
        }
    }

    private function slugifyName(string $name): string
    {
        $slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }

    /**
     * @return Collection|ProductAttributeGroup[]
     */
    public function getProductAttributeGroups(): Collection
    {
        return $this->productAttributeGroups;
    }

    public function addProductAttributeGroup(ProductAttributeGroup $productAttributeGroup): self
    {
        if (!$this->productAttributeGroups->contains($productAttributeGroup)) {
            $this->productAttributeGroups[] = $productAttributeGroup;
            $productAttributeGroup->setProduct($this);
        }

        return $this;
    }

    public function removeProductAttributeGroup(ProductAttributeGroup $productAttributeGroup): self
    {
        if ($this->productAttributeGroups->contains($productAttributeGroup)) {
            $this->productAttributeGroups->removeElement($productAttributeGroup);
            // set the owning side to null (unless already changed)
            if ($productAttributeGroup->getProduct() === $this) {
                $productAttributeGroup->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ProductToCategory[]
     */
    public function getCategory(): Collection
    {
        return $this->category;
    }

    public function addCategory(ProductToCategory $category): self
    {
        if (!$this->category->contains($category)) {
            $this->category[] = $category;
            $category->setProduct($this);
        }

        return $this;
    }

    public function removeCategory(ProductToCategory $category): self
    {
        if ($this->category->contains($category)) {
            $this->category->removeElement($category);
            // set the owning side to null (unless already changed)
            if ($category->getProduct() === $this) {
                $category->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ProductImage[]
     */
    public function getImage(): Collection
    {
        return $this->image;
    }

    public function addImage(ProductImage $image): self
    {
        if (!$this->image->contains($image)) {
            $this->image[] = $image;
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): self
    {
        if ($this->image->contains($image)) {
            $this->image->removeElement($image);
            // set the owning side to null (unless already changed)
            if ($image->getProduct() === $this) {
                $image->setProduct(null);
            }
        }

        return $this;
    }

    public function getOptionsJson(): ?array
    {
        return $this->optionsJson ?? [];
    }

    public function setOptionsJson(?array $optionsJson): self
    {
        $this->optionsJson = $optionsJson;

        return $this;
    }

    public function getAttributeJson(): ?array
    {
        return $this->attributeJson ?? [];
    }

    public function setAttributeJson(?array $attributeJson): self
    {
        $this->attributeJson = $attributeJson;

        return $this;
    }

    public function getCode(): ?Ulid
    {
        return $this->code;
    }

    #[ORM\PrePersist]
    public function setCode(): void
    {
        if ($this->code === null) {
            $this->code = new Ulid();
        }
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

    /**
     * @return Collection|Carousel[]
     */
    public function getCarousels(): Collection
    {
        return $this->carousels;
    }

    public function addCarousel(Carousel $carousel): self
    {
        if (!$this->carousels->contains($carousel)) {
            $this->carousels[] = $carousel;
            $carousel->setProduct($this);
        }

        return $this;
    }

    public function removeCarousel(Carousel $carousel): self
    {
        if ($this->carousels->removeElement($carousel)) {
            // set the owning side to null (unless already changed)
            if ($carousel->getProduct() === $this) {
                $carousel->setProduct(null);
            }
        }

        return $this;
    }

}
