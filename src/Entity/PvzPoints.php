<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * PvzPoints
 */

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/pvz-points',
            normalizationContext: ['groups' => ['pvzPoint:admin:get']],
            paginationClientEnabled: true,
            paginationClientItemsPerPage: true
        ),
        new Get(
            uriTemplate: '/admin/pvz-points/{id}',
            normalizationContext: ['groups' => ['pvzPoint:admin:get']]
        ),
        new Patch(
            uriTemplate: '/admin/pvz-points/{id}'
        ),
        new Put(
            uriTemplate: '/admin/pvz-points/{id}'
        ),
        new Delete(
            uriTemplate: '/admin/pvz-points/{id}'
        ),
    ],
    order: ['city' => 'ASC']
)]
#[ApiFilter(SearchFilter::class,
    properties: ['city' => 'partial']
)]

#[ORM\Table(name: 'pvz_points')]
#[ORM\Index(name: 'city', columns: ['city'])]
#[ORM\Entity]
class PvzPoints
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['pvzPoint:admin:get'])]
    private $id;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'code', type: 'string', length: 255, nullable: true)]
    private $code;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'name', type: 'string', length: 255, nullable: true)]
    private $name;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'city_code', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPoint:admin:get'])]
    private $cityCode;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'address', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPoint:admin:get'])]
    private $address;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'tariff_zone', type: 'string', length: 255, nullable: true)]
    private $tariffZone;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'price', type: 'integer', nullable: true)]
    private $price;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'delivery_period', type: 'integer', nullable: true)]
    private $deliveryPeriod;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'phone', type: 'string', length: 255, nullable: true)]
    private $phone;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'region', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPoint:admin:get'])]
    private $region;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'type_of_office', type: 'string', length: 20, nullable: true)]
    private $typeOfOffice;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'metro', type: 'string', length: 255, nullable: true)]
    private $metro;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'only_prepaid_orders', type: 'string', length: 5, nullable: true)]
    private $onlyPrepaidOrders;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'postal', type: 'integer', nullable: true)]
    private $postal;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'city', type: 'string', length: 255, nullable: true)]
    #[Groups(['pvzPoint:admin:get'])]
    private $city;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'time', type: 'string', length: 255, nullable: true)]
    private $time;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'card', type: 'integer', nullable: true)]
    private $card;

    /**
     * @var float|null
     */
    #[ORM\Column(name: 'shirota', type: 'float', precision: 10, scale: 0, nullable: true)]
    private $shirota;

    /**
     * @var float|null
     */
    #[ORM\Column(name: 'dolgota', type: 'float', precision: 10, scale: 0, nullable: true)]
    private $dolgota;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'company', type: 'string', length: 20, nullable: true)]
    private $company;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCityCode(): ?string
    {
        return $this->cityCode;
    }

    public function setCityCode(?string $cityCode): self
    {
        $this->cityCode = $cityCode;

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

    public function getTariffZone(): ?string
    {
        return $this->tariffZone;
    }

    public function setTariffZone(?string $tariffZone): self
    {
        $this->tariffZone = $tariffZone;

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

    public function getDeliveryPeriod(): ?int
    {
        return $this->deliveryPeriod;
    }

    public function setDeliveryPeriod(?int $deliveryPeriod): self
    {
        $this->deliveryPeriod = $deliveryPeriod;

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

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getTypeOfOffice(): ?string
    {
        return $this->typeOfOffice;
    }

    public function setTypeOfOffice(?string $typeOfOffice): self
    {
        $this->typeOfOffice = $typeOfOffice;

        return $this;
    }

    public function getMetro(): ?string
    {
        return $this->metro;
    }

    public function setMetro(?string $metro): self
    {
        $this->metro = $metro;

        return $this;
    }

    public function getOnlyPrepaidOrders(): ?string
    {
        return $this->onlyPrepaidOrders;
    }

    public function setOnlyPrepaidOrders(?string $onlyPrepaidOrders): self
    {
        $this->onlyPrepaidOrders = $onlyPrepaidOrders;

        return $this;
    }

    public function getPostal(): ?int
    {
        return $this->postal;
    }

    public function setPostal(?int $postal): self
    {
        $this->postal = $postal;

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

    public function getTime(): ?string
    {
        return $this->time;
    }

    public function setTime(?string $time): self
    {
        $this->time = $time;

        return $this;
    }

    public function getCard(): ?int
    {
        return $this->card;
    }

    public function setCard(?int $card): self
    {
        $this->card = $card;

        return $this;
    }

    public function getShirota(): ?float
    {
        return $this->shirota;
    }

    public function setShirota(?float $shirota): self
    {
        $this->shirota = $shirota;

        return $this;
    }

    public function getDolgota(): ?float
    {
        return $this->dolgota;
    }

    public function setDolgota(?float $dolgota): self
    {
        $this->dolgota = $dolgota;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }


}


