<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ["name"], name: "category_name_idx")]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

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


