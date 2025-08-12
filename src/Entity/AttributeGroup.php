<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class AttributeGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'attributeGroup', targetEntity: ProductAttributeGroup::class, cascade: ['persist', 'remove'])]
    private Collection $productAttributeGroups;

    public function __construct()
    {
        $this->productAttributeGroups = new ArrayCollection();
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

    public function getProductAttributeGroups(): Collection
    {
        return $this->productAttributeGroups;
    }

    public function addProductAttributeGroup(ProductAttributeGroup $group): self
    {
        if (!$this->productAttributeGroups->contains($group)) {
            $this->productAttributeGroups->add($group);
            $group->setAttributeGroup($this);
        }
        return $this;
    }

    public function removeProductAttributeGroup(ProductAttributeGroup $group): self
    {
        if ($this->productAttributeGroups->removeElement($group)) {
            if ($group->getAttributeGroup() === $this) {
                $group->setAttributeGroup(null);
            }
        }
        return $this;
    }
}


