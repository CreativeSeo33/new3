<?php
declare(strict_types=1);

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Embeddable]
class ProductPrice
{
    #[ORM\Column(name: 'price', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $price = null;

    #[ORM\Column(name: 'sale_price', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $salePrice = null;

    #[ORM\Column(length: 3, options: ['default' => 'RUB'])]
    private string $currency = 'RUB';

    public function __construct(?int $price = null, ?int $salePrice = null, string $currency = 'RUB')
    {
        $this->price = $price;
        $this->salePrice = $salePrice;
        $this->currency = $currency;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): void
    {
        $this->price = $price;
    }

    public function getSalePrice(): ?int
    {
        return $this->salePrice;
    }

    public function setSalePrice(?int $salePrice): void
    {
        $this->salePrice = $salePrice;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getEffectivePrice(): ?int
    {
        return $this->salePrice ?? $this->price;
    }
}


