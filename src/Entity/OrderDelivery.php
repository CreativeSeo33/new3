<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
#[ORM\Entity(repositoryClass: OrderDeliveryRepository::class)]
class OrderDelivery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get'])]
    private $type;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get'])]
    private $address;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get'])]
    private $city;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['order:get'])]
    private $cost;

    #[ORM\OneToOne(targetEntity: Order::class, mappedBy: 'delivery', cascade: ['persist', 'remove'])]
    private $orders;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get'])]
    private $pvz;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Groups(['order:get'])]
    private $isFree;

    #[ORM\Column(type: 'boolean', nullable: true)]
    #[Groups(['order:get'])]
    private $isCustomCalculate;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $pvzCode;

    #[ORM\Column(type: 'date', nullable: true)]
    private $delivery_date;

    #[ORM\Column(type: 'time', nullable: true)]
    private $delivery_time;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCost(): ?int
    {
        return $this->cost;
    }

    public function setCost(?int $cost): self
    {
        $this->cost = $cost;

        return $this;
    }

    public function getOrders(): ?Order
    {
        return $this->orders;
    }

    public function setOrders(?Order $orders): self
    {
        $this->orders = $orders;

        // set (or unset) the owning side of the relation if necessary
        $newDelivery = null === $orders ? null : $this;
        if (null !== $orders && $orders->getDelivery() !== $newDelivery) {
            $orders->setDelivery($newDelivery);
        }

        return $this;
    }

    public function getPvz(): ?string
    {
        return $this->pvz;
    }

    public function setPvz(?string $pvz): self
    {
        $this->pvz = $pvz;

        return $this;
    }

    public function getIsFree(): ?bool
    {
        return $this->isFree;
    }

    public function setIsFree(?bool $isFree): self
    {
        $this->isFree = $isFree;

        return $this;
    }

    public function getIsCustomCalculate(): ?bool
    {
        return $this->isCustomCalculate;
    }

    public function setIsCustomCalculate(?bool $isCustomCalculate): self
    {
        $this->isCustomCalculate = $isCustomCalculate;

        return $this;
    }

    public function getPvzCode(): ?string
    {
        return $this->pvzCode;
    }

    public function setPvzCode(?string $pvzCode): self
    {
        $this->pvzCode = $pvzCode;

        return $this;
    }

    public function getDeliveryDate(): ?\DateTimeInterface
    {
        return $this->delivery_date;
    }

    public function setDeliveryDate(?\DateTimeInterface $delivery_date): self
    {
        $this->delivery_date = $delivery_date;

        return $this;
    }

    public function getDeliveryTime(): ?\DateTimeInterface
    {
        return $this->delivery_time;
    }

    public function setDeliveryTime(?\DateTimeInterface $delivery_time): self
    {
        $this->delivery_time = $delivery_time;

        return $this;
    }
}
