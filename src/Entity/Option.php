<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\OptionValue;
use App\Repository\OptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Symfony\Component\Serializer\Annotation\Groups;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use App\Api\Processor\DeleteOptionRestrictProcessor;

#[ApiResource(
    order: ['sortOrder' => 'ASC'],
    operations: [
        new Get(normalizationContext: ['groups' => ['options_only:get']]),
        new Patch(
            denormalizationContext: ['groups' => ['options_only:patch']]
        ),
        new Delete(processor: DeleteOptionRestrictProcessor::class),
        new Post(),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['options_only:getCollection']],
    denormalizationContext: ['groups' => ['options_only:post']],
)]
/** не хватает attributes={"order"={"sortOrder": "ASC"}} */
#[ORM\Table(name: '`option`')]
#[ORM\Entity(repositoryClass: OptionRepository::class)]
class Option
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['options_only:get', 'options_only:getCollection', 'product:get'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['options_only:get', 'product:get', 'options_only:patch', 'options_only:post', 'options_only:getCollection'])]
    private string $name;

    #[ORM\Column(type: 'integer')]
    #[Groups(['options_only:get', 'product:get', 'options_only:patch', 'options_only:post', 'options_only:getCollection'])]
    private int $sortOrder;

    #[ORM\Column(type: 'string', length: 100, unique: true, nullable: true)]
    #[Groups(['options_only:get', 'product:read', 'options_only:patch', 'options_only:post', 'options_only:getCollection'])]
    private ?string $code = null;

    #[ORM\OneToMany(targetEntity: OptionValue::class, mappedBy: 'optionType')]
    #[Groups(['options_only:getCollection'])]
    #[OrderBy(['sortOrder' => 'ASC'])]
    private Collection $optionValues;


    public function __construct()
    {
        $this->optionValues = new ArrayCollection();
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

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtolower($code);
        return $this;
    }

    /**
     * @return Collection|OptionValue[]
     */
    public function getOptionValues(): Collection
    {
        return $this->optionValues;
    }

    public function addOptionValue(OptionValue $optionValue): self
    {
        if (!$this->optionValues->contains($optionValue)) {
            $this->optionValues[] = $optionValue;
            $optionValue->setOptionType($this);
        }

        return $this;
    }

    public function removeOptionValue(OptionValue $optionValue): self
    {
        if ($this->optionValues->contains($optionValue)) {
            $this->optionValues->removeElement($optionValue);
            // owning side теперь non-nullable, оставляем как есть; удаление значения должно идти отдельно
        }

        return $this;
    }

}
