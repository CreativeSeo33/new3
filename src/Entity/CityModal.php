<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CityModalRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

#[ApiResource(
    normalizationContext: ['groups' => ['cityModal:get']]
)]
/** не хватает attributes={"order"={"sort": "ASC"}} */
#[ORM\Entity(repositoryClass: CityModalRepository::class)]
class CityModal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['cityModal:get'])]
    private $id;

    #[ORM\Column(type: 'bigint', nullable: true)]
    #[Groups(['cityModal:get'])]
    private $fiasId;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['cityModal:get'])]
    private $name;

    #[ORM\Column(type: 'integer')]
    #[Groups(['cityModal:get'])]
    private $sort;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFiasId(): ?string
    {
        return $this->fiasId;
    }

    public function setFiasId(?string $fiasId): self
    {
        $this->fiasId = $fiasId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function setSort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }
}
