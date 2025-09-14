<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

/**
 * FIAS (Федеральная Информационная Адресная Система)
 */
#[ORM\Entity]
#[ORM\Table(name: 'fias')]
#[ORM\Index(columns: ['postalcode'], name: 'postalcode_idx')]
#[ORM\Index(columns: ['offname'], name: 'offname_idx')]
#[ORM\Index(columns: ['level'], name: 'level_idx')]
#[ORM\Index(columns: ['parent_id'], name: 'parent_id_idx')]
#[ORM\Index(columns: ['offname', 'shortname', 'level'], name: 'osl_idx')]
class Fias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'fias_id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'parent_id', type: Types::INTEGER, nullable: false)]
    private int $parentId;

    #[ORM\Column(name: 'postalcode', type: Types::STRING, length: 6, nullable: true)]
    private ?string $postalcode = null;

    #[ORM\Column(name: 'offname', type: Types::STRING, length: 120, nullable: true)]
    private ?string $offname = null;

    #[ORM\Column(name: 'shortname', type: Types::STRING, length: 10, nullable: true)]
    private ?string $shortname = null;

    #[ORM\Column(name: 'level', type: Types::SMALLINT, nullable: false)]
    private int $level;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getPostalcode(): ?string
    {
        return $this->postalcode;
    }

    public function setPostalcode(?string $postalcode): self
    {
        $this->postalcode = $postalcode;
        return $this;
    }

    public function getOffname(): ?string
    {
        return $this->offname;
    }

    public function setOffname(?string $offname): self
    {
        $this->offname = $offname;
        return $this;
    }

    public function getShortname(): ?string
    {
        return $this->shortname;
    }

    public function setShortname(?string $shortname): self
    {
        $this->shortname = $shortname;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Получить полный адрес
     */
    public function getFullAddress(): string
    {
        $parts = [];

        if ($this->offname) {
            $parts[] = $this->offname;
        }

        if ($this->shortname) {
            $parts[] = $this->shortname;
        }

        return implode(' ', $parts);
    }

    /**
     * Получить уровень адреса как строку
     */
    public function getLevelName(): string
    {
        return match ($this->level) {
            0 => 'Страна',
            1 => 'Регион',
            2 => 'Район',
            3 => 'Город',
            4 => 'Населенный пункт',
            5 => 'Улица',
            6 => 'Здание',
            default => 'Неизвестный уровень'
        };
    }
}
