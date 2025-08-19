<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Api\Processor\DeleteOptionValueRestrictProcessor;
use App\Repository\OptionValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    order: ['sortOrder' => 'ASC'],
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(processor: DeleteOptionValueRestrictProcessor::class)
    ]
)]
#[ORM\Table(
    name: 'option_value',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uq_value_code_per_option', columns: ['option_id', 'code'])]
)]
#[ORM\Entity(repositoryClass: OptionValueRepository::class)]
class OptionValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['options', 'product:read', 'options_only:getCollection'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['options', 'product:read', 'options_only:getCollection'])]
    private string $value;

    #[ORM\Column(type: 'integer')]
    #[Groups(['options', 'product:get', 'options_only:getCollection'])]
    private int $sortOrder;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['options', 'product:read', 'options_only:getCollection'])]
    private ?string $code = null;

    #[ORM\ManyToOne(targetEntity: Option::class, inversedBy: 'optionValues')]
    #[ORM\JoinColumn(name: 'option_id', referencedColumnName: 'id', nullable: false)]
    private Option $optionType;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtolower($code);
        return $this;
    }

    public function getOptionType(): Option
    {
        return $this->optionType;
    }

    public function setOptionType(Option $optionType): self
    {
        $this->optionType = $optionType;

        return $this;
    }

}
