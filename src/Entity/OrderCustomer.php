<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrderCustomerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Order;

#[ApiResource(
    normalizationContext: ['groups' => ['orderCustomer:get']]
)]
#[ORM\Table(name: '`order_customer`')]
#[ORM\Entity(repositoryClass: OrderCustomerRepository::class)]
class OrderCustomer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get', 'orderCustomer:get'])]
    #[Assert\NotBlank]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get', 'orderCustomer:get'])]
    #[Assert\NotBlank]
    private $phone;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get', 'orderCustomer:get'])]
    private $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get', 'orderCustomer:get'])]
    private $ip;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get', 'orderCustomer:get'])]
    private $userAgent;

    #[ORM\OneToOne(mappedBy: 'customer', targetEntity: Order::class, cascade: ['persist', 'remove'])]
    private $orders;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private $phoneNormal;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['order:get', 'orderCustomer:get'])]
    private $comment;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $user_agent): self
    {
        $this->userAgent = $user_agent;

        return $this;
    }

    public function getOrders(): ?Order
    {
        return $this->orders;
    }

    public function setOrders(?Order $orders): self
    {
        $this->orders = $orders;


        /*if ($orders->getCustomer() !== $this) {
            $orders->setCustomer($this);
        }*/

        // set (or unset) the owning side of the relation if necessary
        $newCustomer = null === $orders ? null : $this;
        if (null !== $orders && $orders->getCustomer() !== $newCustomer) {
            $orders->setCustomer($newCustomer);
        }

        return $this;
    }

    public function getPhoneNormal(): ?string
    {
        return $this->phoneNormal;
    }

    public function setPhoneNormal(?string $phoneNormal): self
    {
        $this->phoneNormal = $phoneNormal;

        return $this;
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
}
