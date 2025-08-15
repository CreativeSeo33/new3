<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['city:get']],
    paginationClientEnabled: true,
    paginationClientItemsPerPage: true,
    order: ['city' => 'ASC']
)]
#[ApiFilter(OrderFilter::class,
    properties: ['population' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class,
    properties: ['address' => 'partial']
)]

#[ORM\Entity]
class City
{
    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['city:get'])]
    private $id;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'address', type: 'string', length: 255, nullable: true)]
    #[Groups(['city:get'])]
    private $address;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'postal_code', type: 'string', length: 255, nullable: true)]
    private $postalCode;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'federal_district', type: 'string', length: 255, nullable: true)]
    private $federalDistrict;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'region_type', type: 'string', length: 255, nullable: true)]
    private $regionType;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'region', type: 'string', length: 255, nullable: true)]
    private $region;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'city_type', type: 'string', length: 255, nullable: true)]
    private $cityType;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'city', type: 'string', length: 255, nullable: true)]
    #[Groups(['city:get'])]
    private $city;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'kladr_id', type: 'bigint', nullable: true)]
    #[Groups(['city:get'])]
    private $kladrId;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'fias_level', type: 'integer', nullable: true)]
    private $fiasLevel;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'capital_marker', type: 'integer', nullable: true)]
    private $capitalMarker;

    /**
     * @var float|null
     */
    #[ORM\Column(name: 'geo_lat', type: 'float', precision: 10, scale: 0, nullable: true)]
    private $geoLat;

    /**
     * @var float|null
     */
    #[ORM\Column(name: 'geo_lon', type: 'float', precision: 10, scale: 0, nullable: true)]
    private $geoLon;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'population', type: 'bigint', nullable: true)]
    private $population;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getFederalDistrict(): ?string
    {
        return $this->federalDistrict;
    }

    public function setFederalDistrict(?string $federalDistrict): self
    {
        $this->federalDistrict = $federalDistrict;

        return $this;
    }

    public function getRegionType(): ?string
    {
        return $this->regionType;
    }

    public function setRegionType(?string $regionType): self
    {
        $this->regionType = $regionType;

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

    public function getCityType(): ?string
    {
        return $this->cityType;
    }

    public function setCityType(?string $cityType): self
    {
        $this->cityType = $cityType;

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

    public function getKladrId(): ?string
    {
        return $this->kladrId;
    }

    public function setKladrId(?string $kladrId): self
    {
        $this->kladrId = $kladrId;

        return $this;
    }

    public function getFiasLevel(): ?int
    {
        return $this->fiasLevel;
    }

    public function setFiasLevel(?int $fiasLevel): self
    {
        $this->fiasLevel = $fiasLevel;

        return $this;
    }

    public function getCapitalMarker(): ?int
    {
        return $this->capitalMarker;
    }

    public function setCapitalMarker(?int $capitalMarker): self
    {
        $this->capitalMarker = $capitalMarker;

        return $this;
    }

    public function getGeoLat(): ?float
    {
        return $this->geoLat;
    }

    public function setGeoLat(?float $geoLat): self
    {
        $this->geoLat = $geoLat;

        return $this;
    }

    public function getGeoLon(): ?float
    {
        return $this->geoLon;
    }

    public function setGeoLon(?float $geoLon): self
    {
        $this->geoLon = $geoLon;

        return $this;
    }

    public function getPopulation(): ?string
    {
        return $this->population;
    }

    public function setPopulation(?string $population): self
    {
        $this->population = $population;

        return $this;
    }


}
