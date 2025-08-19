<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Repository\ProductOptionValueAssignmentRepository;

#[ORM\Entity(repositoryClass: ProductOptionValueAssignmentRepository::class)]
#[ORM\Table(
    name: 'product_option_value_assignment',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_product_value', columns: ['product_id', 'value_id'])
    ],
    indexes: [
        new ORM\Index(name: 'idx_pova_option_value', columns: ['option_id', 'value_id']),
        new ORM\Index(name: 'idx_pova_height', columns: ['height']),
        new ORM\Index(name: 'idx_pova_price', columns: ['price']),
        new ORM\Index(name: 'idx_pova_bulbs', columns: ['bulbs_count']),
        new ORM\Index(name: 'idx_pova_area', columns: ['lighting_area'])
    ]
)]
class ProductOptionValueAssignment
{
    #[ORM\Id, ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'optionAssignments')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Option::class)]
    #[ORM\JoinColumn(name: 'option_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['product:read'])]
    private Option $option;

    #[ORM\ManyToOne(targetEntity: OptionValue::class)]
    #[ORM\JoinColumn(name: 'value_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['product:read'])]
    private OptionValue $value;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['product:read'])]
    private ?int $height = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['product:read'])]
    private ?int $price = null;

    #[ORM\Column(name: 'bulbs_count', type: 'integer', nullable: true)]
    #[Groups(['product:read'])]
    private ?int $bulbsCount = null;

    #[ORM\Column(name: 'lighting_area', type: 'integer', nullable: true)]
    #[Groups(['product:read'])]
    private ?int $lightingArea = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['product:read'])]
    private ?string $sku = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read'])]
    private ?array $attributes = [];

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

    public function getOption(): Option
    {
        return $this->option;
    }

    public function setOption(Option $option): self
    {
        $this->option = $option;
        return $this;
    }

    public function getValue(): OptionValue
    {
        return $this->value;
    }

    public function setValue(OptionValue $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;
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

    public function getBulbsCount(): ?int
    {
        return $this->bulbsCount;
    }

    public function setBulbsCount(?int $bulbsCount): self
    {
        $this->bulbsCount = $bulbsCount;
        return $this;
    }

    public function getLightingArea(): ?int
    {
        return $this->lightingArea;
    }

    public function setLightingArea(?int $lightingArea): self
    {
        $this->lightingArea = $lightingArea;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes ?? [];
    }

    public function setAttributes(?array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }
}


