<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\PvzPriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['pvzPrice:get']]
)]

#[ORM\Table(name: 'pvz_price')]
#[ORM\Index(name: 'city', columns: ['city'])]
#[ORM\Entity(repositoryClass: PvzPriceRepository::class)]
class PvzPrice
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'city', type: 'string', length: 255, nullable: false)]
    #[Groups(['pvzPrice:get'])]
    private $city;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'srok', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPrice:get'])]
    private $srok;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'city2', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPrice:get'])]
    private $city2;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'code', type: 'string', length: 20, nullable: true)]
    private $code;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'alias', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPrice:get'])]
    private $alias;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'region', type: 'string', length: 255, nullable: true)]
    private $region;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'cost', type: 'integer', nullable: true)]
    #[Groups(['pvzPrice:get'])]
    private $cost;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'free', type: 'integer', nullable: true)]
    #[Groups(['pvzPrice:get'])]
    private $free;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'calculate_price', type: 'integer', nullable: true)]
    private $calculatePrice;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'calculate_delivery_period', type: 'string', length: 255, nullable: true)]
    private $calculateDeliveryPeriod;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getSrok(): ?string
    {
        return $this->srok;
    }

    public function setSrok(?string $srok): self
    {
        $this->srok = $srok;

        return $this;
    }

    public function getCity2(): ?string
    {
        return $this->city2;
    }

    public function setCity2(?string $city2): self
    {
        $this->city2 = $city2;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(?string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

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

    public function getFree(): ?int
    {
        return $this->free;
    }

    public function setFree(?int $free): self
    {
        $this->free = $free;

        return $this;
    }

    public function getCalculatePrice(): ?int
    {
        return $this->calculatePrice;
    }

    public function setCalculatePrice(?int $calculatePrice): self
    {
        $this->calculatePrice = $calculatePrice;

        return $this;
    }

    public function getCalculateDeliveryPeriod(): ?string
    {
        return $this->calculateDeliveryPeriod;
    }

    public function setCalculateDeliveryPeriod(?string $calculateDeliveryPeriod): self
    {
        $this->calculateDeliveryPeriod = $calculateDeliveryPeriod;

        return $this;
    }
}


