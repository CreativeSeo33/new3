<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\ORM\Mapping\UniqueConstraint;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\UniqueConstraint(name: 'customer_id', columns: ['customer_id',])]
#[ORM\UniqueConstraint(name: 'delivery_id', columns: ['delivery_id',])]
#[ORM\HasLifecycleCallbacks]
#[ApiFilter(OrderFilter::class,
    properties: ['dateAdded'],
    arguments: ['orderParameterName' => 'order']
)]
#[ApiFilter(SearchFilter::class,
    properties: ['orderId' => 'partial','status' => 'exact']
)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['order:get']],),
        new Delete(),
        new Patch(
            denormalizationContext: ['groups' => ['order:patch']]
        ),
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['order:get']],
)]
/** не хватает attributes={"order"={"dateAdded": "DESC"}} */

class Order
{
    public const STATUS_SUCCESS = 1;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['order:get'])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(['order:get'])]
    private ?int $orderId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['order:get'])]
    private ?\DateTimeInterface $dateAdded = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:get'])]
    private ?string $comment = null;

    #[ORM\OneToMany(mappedBy: 'orders', targetEntity: OrderProducts::class, cascade: ['all'])]
    #[Groups(['order:get'])]
    private $products;

    #[ORM\OneToOne(inversedBy: 'orders', targetEntity: OrderCustomer::class, cascade: ['all'], fetch: 'LAZY')]
    #[Groups(['order:get'])]
    #[ApiFilter(SearchFilter::class, properties: ['customer.name' => 'partial', 'customer.phone' => 'partial'])]
    private OrderCustomer $customer;

    #[ORM\OneToOne(inversedBy: 'orders', targetEntity: OrderDelivery::class, cascade: ['all'], fetch: 'LAZY')]
    #[Groups(['order:get'])]
    private OrderDelivery $delivery;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:get', 'order:patch'])]
    private ?int $status = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['order:get'])]
    private ?int $total = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['order:get'])]
    private ?User $user = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderId(): ?int
    {
        return $this->orderId;
    }

    public function setOrderId(int $order_id): self
    {
        $this->orderId = $order_id;

        return $this;
    }

    public function getDateAdded(): ?\DateTimeInterface
    {
        return $this->dateAdded;
    }

    #[ORM\PrePersist]
    public function setDateAdded()
    {
        $this->dateAdded = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setDefaultStatus()
    {
        if ($this->status === null) {
            $this->status = self::STATUS_SUCCESS;
        }
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return Collection|OrderProducts[]
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(OrderProducts $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
            $product->setOrders($this);
        }

        return $this;
    }

    public function removeProduct(OrderProducts $product): self
    {
        if ($this->products->contains($product)) {
            $this->products->removeElement($product);
            // set the owning side to null (unless already changed)
            if ($product->getOrders() === $this) {
                $product->setOrders(null);
            }
        }

        return $this;
    }

    public function getCustomer(): ?OrderCustomer
    {
        return $this->customer;
    }

    public function setCustomer(?OrderCustomer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getDelivery(): ?OrderDelivery
    {
        return $this->delivery;
    }

    public function setDelivery(?OrderDelivery $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(?int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

}
