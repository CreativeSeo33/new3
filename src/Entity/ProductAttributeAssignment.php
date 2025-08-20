<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\ProductAttributeAssignmentRepository;
use App\Validator\ConsistentAttributeAssignment;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductAttributeAssignmentRepository::class)]
#[ORM\Table(
    name: 'product_attribute_assignment',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uq_paa_product_attr_pos', columns: ['product_id','attribute_id','position'])
    ],
    indexes: [
        new ORM\Index(name: 'idx_paa_product', columns: ['product_id']),
        new ORM\Index(name: 'idx_paa_attribute', columns: ['attribute_id']),
        new ORM\Index(name: 'idx_paa_group', columns: ['attribute_group_id']),
        new ORM\Index(name: 'idx_paa_int', columns: ['int_value']),
        new ORM\Index(name: 'idx_paa_decimal', columns: ['decimal_value']),
        new ORM\Index(name: 'idx_paa_bool', columns: ['bool_value']),
    ]
)]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(denormalizationContext: ['groups' => ['paa:write']]),
        new Patch(denormalizationContext: ['groups' => ['paa:write']]),
        new Delete()
    ],
    normalizationContext: ['groups' => ['paa:read']]
)]
#[ConsistentAttributeAssignment]
class ProductAttributeAssignment
{
    #[ORM\Id, ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['paa:read','product:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'attributeAssignments')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['paa:write'])]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?Attribute $attribute = null;

    #[ORM\ManyToOne(targetEntity: AttributeGroup::class)]
    #[ORM\JoinColumn(name: 'attribute_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?AttributeGroup $attributeGroup = null;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'string'])]
    #[Assert\Choice(choices: ['string','text','int','decimal','bool','json','date'])]
    #[Groups(['paa:read','paa:write','product:read'])]
    private string $dataType = 'string';

    #[ORM\Column(name: 'string_value', type: 'string', length: 255, nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?string $stringValue = null;

    #[ORM\Column(name: 'text_value', type: Types::TEXT, nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?string $textValue = null;

    #[ORM\Column(name: 'int_value', type: 'integer', nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?int $intValue = null;

    #[ORM\Column(name: 'decimal_value', type: 'decimal', precision: 15, scale: 4, nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?string $decimalValue = null;

    #[ORM\Column(name: 'bool_value', type: 'boolean', nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?bool $boolValue = null;

    #[ORM\Column(name: 'date_value', type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?\DateTimeInterface $dateValue = null;

    #[ORM\Column(name: 'json_value', type: Types::JSON, nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?array $jsonValue = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?string $unit = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\GreaterThanOrEqual(0)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private int $position = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['paa:read','paa:write','product:read'])]
    private ?int $sortOrder = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAttribute(): ?Attribute
    {
        return $this->attribute;
    }

    public function setAttribute(?Attribute $attribute): self
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getAttributeGroup(): ?AttributeGroup
    {
        return $this->attributeGroup;
    }

    public function setAttributeGroup(?AttributeGroup $attributeGroup): self
    {
        $this->attributeGroup = $attributeGroup;
        return $this;
    }

    public function getDataType(): string
    {
        return $this->dataType;
    }

    public function setDataType(string $dataType): self
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function getStringValue(): ?string
    {
        return $this->stringValue;
    }

    public function setStringValue(?string $stringValue): self
    {
        $this->stringValue = $stringValue;
        return $this;
    }

    public function getTextValue(): ?string
    {
        return $this->textValue;
    }

    public function setTextValue(?string $textValue): self
    {
        $this->textValue = $textValue;
        return $this;
    }

    public function getIntValue(): ?int
    {
        return $this->intValue;
    }

    public function setIntValue(?int $intValue): self
    {
        $this->intValue = $intValue;
        return $this;
    }

    public function getDecimalValue(): ?string
    {
        return $this->decimalValue;
    }

    public function setDecimalValue(?string $decimalValue): self
    {
        $this->decimalValue = $decimalValue;
        return $this;
    }

    public function getBoolValue(): ?bool
    {
        return $this->boolValue;
    }

    public function setBoolValue(?bool $boolValue): self
    {
        $this->boolValue = $boolValue;
        return $this;
    }

    public function getDateValue(): ?\DateTimeInterface
    {
        return $this->dateValue;
    }

    public function setDateValue(?\DateTimeInterface $dateValue): self
    {
        $this->dateValue = $dateValue;
        return $this;
    }

    public function getJsonValue(): ?array
    {
        return $this->jsonValue;
    }

    public function setJsonValue(?array $jsonValue): self
    {
        $this->jsonValue = $jsonValue;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
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


