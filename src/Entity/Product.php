<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
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
use App\Entity\ProductSeo;
use App\Entity\Embeddable\ProductPrice;
use App\Entity\Embeddable\ProductTimestamps;
use App\Entity\Manufacturer;
use App\Entity\ProductOptionValueAssignment;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Index(columns: ["name"], name: 'name')]
#[ORM\Index(columns: ["status"], name: 'product_status_idx')]
#[ORM\Index(columns: ["date_added"], name: 'product_date_added_idx')]
#[ORM\Index(columns: ["sort_order"], name: 'product_sort_order_idx')]
#[ORM\Index(columns: ["status", "date_added"], name: 'idx_product_status_created')]
#[ORM\Index(columns: ["effective_price"], name: 'product_effective_price_idx')]
#[UniqueEntity(fields: ['code'], message: 'Product code must be unique')]
#[UniqueEntity(fields: ['slug'], message: 'Slug must be unique')]
#[Assert\Expression(
    expression: 'this.getSalePrice() === null or this.getPrice() === null or this.getSalePrice() <= this.getPrice()',
    message: 'Sale price must be less than or equal to price'
)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['product:read']]),
        new Post(denormalizationContext: ['groups' => ['product:create']], validationContext: ['groups' => ['product:create']]),
        new Delete(),
        new Patch(denormalizationContext: ['groups' => ['product:update']], validationContext: ['groups' => ['product:update']]),
        new GetCollection(normalizationContext: ['groups' => ['product:list']])
    ],
    normalizationContext: ['groups' => ['product:read']]
)]
#[ApiFilter(OrderFilter::class,
    properties: ['dateAdded','status','sortOrder','effectivePrice'],
    arguments: ['orderParameterName' => 'order']
)]
#[ApiFilter(SearchFilter::class,
    properties: ['name' => 'partial','manufacturerRef' => 'exact','manufacturerRef.name' => 'partial']
)]
#[ApiFilter(BooleanFilter::class,
    properties: ['status' => 'exact']
)]
#[ApiFilter(RangeFilter::class, properties: [
    'optionAssignments.height',
    'optionAssignments.price',
    'optionAssignments.bulbsCount',
    'optionAssignments.lightingArea'
])]
#[ApiFilter(SearchFilter::class, properties: [
    'optionAssignments.option.code' => 'exact',
    'optionAssignments.value.code' => 'exact'
])]
class Product
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['product:read', 'product:list', 'product_option:post'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['product:read', 'product:update', 'product:create', 'product:list'])]
    #[Assert\NotBlank(groups: ['product:create'])]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    #[Groups(['product:read', 'product:update', 'product:create', 'product:list'])]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/i', message: 'Slug format is invalid')]
    private ?string $slug = null;

    #[ORM\Embedded(class: ProductPrice::class, columnPrefix: false)]
    private ProductPrice $pricing;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:read', 'product:update', 'product:create', 'product:list'])]
    private ?int $sortOrder = null;

    // SEO перенесено в ProductSeo. Оставляем прокси-методы ниже.

    // Новая ссылка на производителя
    #[ORM\ManyToOne(targetEntity: Manufacturer::class)]
    #[ORM\JoinColumn(name: 'manufacturer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['product:read', 'product:update', 'product:create'])]
    private ?Manufacturer $manufacturerRef = null;

    #[ORM\Column(name: 'effective_price', nullable: true)]
    #[Groups(['product:read', 'product:list'])]
    private ?int $effectivePrice = null;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['product:read', 'product:update', 'product:create', 'product:list'])]
    private ?bool $status = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['product:read', 'product:update', 'product:create'])]
    #[Assert\PositiveOrZero]
    private ?int $quantity = null;

    // H1 перенесено в ProductSeo. Оставляем прокси-методы ниже.

    #[ORM\Embedded(class: ProductTimestamps::class, columnPrefix: false)]
    private ProductTimestamps $timestamps;


    #[ORM\OneToOne(mappedBy: 'product', targetEntity: ProductSeo::class, cascade: ['persist', 'remove'])]
    private ?ProductSeo $seo = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductAttributeGroup::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:read', 'product:create'])]
    private Collection $productAttributeGroups;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductToCategory::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:read', 'product:create'])]
    #[ApiFilter(SearchFilter::class,
        properties: ['category.category' => 'exact']
    )]
    private Collection $category;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductImage::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:read', 'product:create', 'order:get'])]
    #[OrderBy(['sortOrder' => 'ASC'])]
    private Collection $image;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read', 'product:update', 'product_option:post', 'product:create'])]
    private ?array $optionsJson = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read', 'product:update', 'product_option:post', 'product:create'])]
    private ?array $attributeJson = [];

    #[Assert\Ulid]
    #[ORM\Column(type: UlidType::NAME, unique: true, nullable: true)]
    #[Groups(['product:read'])]
    private ?Ulid $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:read', 'product:update'])]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Carousel::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[Groups(['product:read', 'product:create', 'order:get'])]
    #[OrderBy(['sort' => 'ASC'])]
    private Collection $carousels;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductOptionValueAssignment::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[OrderBy(['id' => 'ASC'])]
    #[Groups(['product:read', 'product:create', 'product:update'])]
    private Collection $optionAssignments;

    public function __construct()
    {
        $this->productAttributeGroups = new ArrayCollection();
        $this->category = new ArrayCollection();
        $this->image = new ArrayCollection();
        $this->carousels = new ArrayCollection();
        $this->pricing = new ProductPrice();
        $this->timestamps = new ProductTimestamps();
        $this->optionAssignments = new ArrayCollection();
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
        return $this->pricing->getPrice();
    }

    public function setPrice(?int $price): self
    {
        $this->pricing->setPrice($price);
        return $this;
    }

    public function getSalePrice(): ?int
    {
        return $this->pricing->getSalePrice();
    }

    public function setSalePrice(?int $salePrice): self
    {
        $this->pricing->setSalePrice($salePrice);
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

    #[Groups(['product:read', 'product:list'])]
    public function getMetaTitle(): ?string
    {
        return $this->seo?->getMetaTitle();
    }

    #[Groups(['product:update','product:create'])]
    public function setMetaTitle(?string $metaTitle): self
    {
        if ($metaTitle === null && $this->seo === null) {
            return $this;
        }
        $this->ensureSeo()->setMetaTitle($metaTitle);
        return $this;
    }

    #[Groups(['product:read', 'product:list'])]
    public function getMetaDescription(): ?string
    {
        return $this->seo?->getMetaDescription();
    }

    #[Groups(['product:update','product:create'])]
    public function setMetaDescription(?string $metaDescription): self
    {
        if ($metaDescription === null && $this->seo === null) {
            return $this;
        }
        $this->ensureSeo()->setMetaDescription($metaDescription);
        return $this;
    }

    #[Groups(['product:read', 'product:list'])]
    public function getMetaKeywords(): ?string
    {
        return $this->seo?->getMetaKeywords();
    }

    #[Groups(['product:update','product:create'])]
    public function setMetaKeywords(?string $metaKeywords): self
    {
        if ($metaKeywords === null && $this->seo === null) {
            return $this;
        }
        $this->ensureSeo()->setMetaKeywords($metaKeywords);
        return $this;
    }

    public function getManufacturerRef(): ?Manufacturer
    {
        return $this->manufacturerRef;
    }

    public function setManufacturerRef(?Manufacturer $manufacturer): self
    {
        $this->manufacturerRef = $manufacturer;
        return $this;
    }

    public function getEffectivePrice(): ?int
    {
        return $this->effectivePrice ?? ($this->pricing->getSalePrice() ?? $this->pricing->getPrice());
    }

    public function setEffectivePrice(?int $price): self
    {
        $this->effectivePrice = $price;
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

    #[Groups(['product:read', 'product:list'])]
    public function getMetaH1(): ?string
    {
        return $this->seo?->getH1();
    }

    #[Groups(['product:update','product:create'])]
    public function setMetaH1(?string $metaH1): self
    {
        if ($metaH1 === null && $this->seo === null) {
            return $this;
        }
        $this->ensureSeo()->setH1($metaH1);
        return $this;
    }

    private function ensureSeo(): ProductSeo
    {
        if ($this->seo === null) {
            $this->seo = new ProductSeo($this);
        }
        return $this->seo;
    }

    public function getDateAdded(): ?\DateTimeInterface
    {
        return $this->timestamps->getCreatedAt();
    }

    public function setDateAdded(): void
    {
        if ($this->timestamps->getCreatedAt() === null) {
            $this->timestamps->setCreatedAt(new \DateTime());
        }
    }

    public function getDateEdited(): ?\DateTimeInterface
    {
        return $this->timestamps->getUpdatedAt();
    }

    public function setDateEdited(?\DateTimeInterface $date_edited): self
    {
        $this->timestamps->setUpdatedAt($date_edited);
        return $this;
    }

    public function touchUpdatedAt(): void
    {
        $this->timestamps->setUpdatedAt(new \DateTime());
    }

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
     * Replaces the embedded pricing to ensure Doctrine detects changes reliably.
     */
    public function setPricingValues(?int $price, ?int $salePrice): self
    {
        $currency = $this->pricing->getCurrency();
        $this->pricing = new ProductPrice($price, $salePrice, $currency);
        return $this;
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

    public function getSeo(): ?ProductSeo
    {
        return $this->seo;
    }

    public function setSeo(?ProductSeo $seo): self
    {
        $this->seo = $seo;
        if ($seo !== null && $seo->getProduct() !== $this) {
            $seo->setProduct($this);
        }

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

    /**
     * @return Collection|ProductOptionValueAssignment[]
     */
    public function getOptionAssignments(): Collection
    {
        return $this->optionAssignments;
    }

    public function addOptionAssignment(ProductOptionValueAssignment $assignment): self
    {
        if (!$this->optionAssignments->contains($assignment)) {
            $this->optionAssignments[] = $assignment;
            $assignment->setProduct($this);
        }
        return $this;
    }

    public function removeOptionAssignment(ProductOptionValueAssignment $assignment): self
    {
        $this->optionAssignments->removeElement($assignment);
        return $this;
    }

    #[Groups(['product:read'])]
    public function getOptionsStructured(): array
    {
        $out = [];
        foreach ($this->optionAssignments as $a) {
            $optCode = $a->getOption()->getCode();
            $out[$optCode][] = [
                'optionCode'    => $optCode,
                'optionName'    => $a->getOption()->getName(),
                'valueCode'     => $a->getValue()->getCode(),
                'value'         => $a->getValue()->getValue(),
                'height'        => $a->getHeight(),
                'price'         => $a->getPrice(),
                'bulbs_count'   => $a->getBulbsCount(),
                'lighting_area' => $a->getLightingArea(),
                'sku'           => $a->getSku(),
                'attributes'    => $a->getAttributes(),
            ];
        }
        return $out;
    }

}
