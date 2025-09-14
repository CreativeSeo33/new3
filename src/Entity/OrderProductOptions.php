<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderProductOptionsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource]
#[ORM\Table]
#[ORM\Index(name: 'product_id', columns: ['product_id'])]
#[ORM\Entity(repositoryClass: OrderProductOptionsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderProductOptions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $product_id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get'])]
    private $optionName;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['order:get'])]
    private $value = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['order:get'])]
    private $price;

    #[ORM\ManyToOne(targetEntity: OrderProducts::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'order_product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $product;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): ?int
    {
        return $this->product_id;
    }

    public function setProductId(?int $product_id): self
    {
        $this->product_id = $product_id;

        return $this;
    }

    public function getOptionName(): ?string
    {
        return $this->optionName;
    }

    public function setOptionName(?string $option_name): self
    {
        $this->optionName = $option_name;

        return $this;
    }

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function setValue(array $value): self
    {
        $this->value = $value;

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

    public function getProduct(): ?OrderProducts
    {
        return $this->product;
    }

    public function setProduct(?OrderProducts $product): self
    {
        $this->product = $product;

        return $this;
    }

}
