<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\FacetConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FacetConfigRepository::class)]
#[ORM\Table(name: 'facet_config', indexes: [
    new ORM\Index(name: 'idx_fc_scope', columns: ['scope']),
])]
#[UniqueEntity(fields: ['category'], message: 'Фасетная конфигурация для категории уже существует.')]
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
    ]
)]
class FacetConfig
{
    public const SCOPE_GLOBAL = 'GLOBAL';
    public const SCOPE_CATEGORY = 'CATEGORY';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    #[Assert\Choice(choices: [self::SCOPE_GLOBAL, self::SCOPE_CATEGORY])]
    private string $scope = self::SCOPE_CATEGORY;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Category $category = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attributes = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $showZeros = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $collapsedByDefault = true;

    #[ORM\Column(type: 'integer', options: ['default' => 20])]
    private int $valuesLimit = 20;

    #[ORM\Column(length: 16, options: ['default' => 'popularity'])]
    #[Assert\Choice(choices: ['popularity','alpha','manual'])]
    private string $valuesSort = 'popularity';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        if ($scope === self::SCOPE_GLOBAL) {
            $this->category = null;
        }
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes ?? [];
    }

    public function setAttributes(?array $attributes): self
    {
        $this->attributes = $attributes ?? [];
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options ?? [];
        return $this;
    }

    public function isShowZeros(): bool
    {
        return $this->showZeros;
    }

    public function setShowZeros(bool $showZeros): self
    {
        $this->showZeros = $showZeros;
        return $this;
    }

    public function isCollapsedByDefault(): bool
    {
        return $this->collapsedByDefault;
    }

    public function setCollapsedByDefault(bool $collapsedByDefault): self
    {
        $this->collapsedByDefault = $collapsedByDefault;
        return $this;
    }

    public function getValuesLimit(): int
    {
        return $this->valuesLimit;
    }

    public function setValuesLimit(int $valuesLimit): self
    {
        $this->valuesLimit = $valuesLimit;
        return $this;
    }

    public function getValuesSort(): string
    {
        return $this->valuesSort;
    }

    public function setValuesSort(string $valuesSort): self
    {
        $this->valuesSort = $valuesSort;
        return $this;
    }
}


