<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderProductsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
#[ORM\Table]
#[ORM\Index(columns: ['orders_id'], name: 'orders_id')]
#[ORM\Entity(repositoryClass: OrderProductsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderProducts
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    #[Groups(['order:get'])]
    private $product_id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['order:get'])]
    private $product_name;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['order:get'])]
    private $price;

    #[ORM\Column(type: 'integer')]
    #[Groups(['order:get'])]
    private $quantity;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'products')]
    private $orders;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: OrderProductOptions::class, cascade: ['remove'])]
    #[Groups(['order:get'])]
    private $options;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['order:get'])]
    private $salePrice;

    public function __construct()
    {
        $this->options = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): ?int
    {
        return $this->product_id;
    }

    public function setProductId(int $product_id): self
    {
        $this->product_id = $product_id;

        return $this;
    }

    public function getProductName(): ?string
    {
        return $this->product_name;
    }

    public function setProductName(string $product_name): self
    {
        $this->product_name = $product_name;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getOrders(): ?Order
    {
        return $this->orders;
    }

    public function setOrders(?Order $orders): self
    {
        $this->orders = $orders;

        return $this;
    }

    /**
     * @return Collection|OrderProductOptions[]
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(OrderProductOptions $option): self
    {
        if (!$this->options->contains($option)) {
            $this->options[] = $option;
            $option->setProduct($this);
        }

        return $this;
    }

    public function removeOption(OrderProductOptions $option): self
    {
        if ($this->options->contains($option)) {
            $this->options->removeElement($option);
            // set the owning side to null (unless already changed)
            if ($option->getProduct() === $this) {
                $option->setProduct(null);
            }
        }

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

}
