<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OptionValueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(order: ['sortOrder' => 'ASC'])]
#[ORM\Entity(repositoryClass: OptionValueRepository::class)]
class OptionValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['options', 'options_only:getCollection'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['options', 'product:get', 'options_only:getCollection'])]
    private string $value;

    #[ORM\Column(type: 'integer')]
    #[Groups(['options', 'product:get', 'options_only:getCollection'])]
    private int $sortOrder;

    #[ORM\ManyToOne(targetEntity: Option::class, inversedBy: 'optionValues')]
    #[ORM\JoinColumn(onDelete: 'SET NULL', nullable: true)]
    private ?Option $optionType = null;

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

    public function getOptionType(): ?Option
    {
        return $this->optionType;
    }

    public function setOptionType(?Option $optionType): self
    {
        $this->optionType = $optionType;

        return $this;
    }

}
