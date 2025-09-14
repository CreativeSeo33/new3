<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Fias;
use App\Repository\FiasRepository;

/**
 * Сервис для работы с данными FIAS
 */
class FiasService
{
    public function __construct(
        private FiasRepository $fiasRepository
    ) {}

    /**
     * Найти адрес по почтовому индексу
     */
    public function findByPostalcode(string $postalcode): array
    {
        return $this->fiasRepository->findByPostalcode($postalcode);
    }

    /**
     * Найти адрес по названию
     */
    public function findByName(string $name, ?int $level = null): array
    {
        return $this->fiasRepository->findByName($name, $level);
    }

    /**
     * Получить список регионов
     */
    public function getRegions(): array
    {
        return $this->fiasRepository->findRegions();
    }

    /**
     * Получить города региона
     */
    public function getCities(?int $regionId = null): array
    {
        return $this->fiasRepository->findCities($regionId);
    }

    /**
     * Получить населенные пункты города
     */
    public function getSettlements(?int $cityId = null): array
    {
        return $this->fiasRepository->findSettlements($cityId);
    }

    /**
     * Получить улицы населенного пункта
     */
    public function getStreets(?int $settlementId = null): array
    {
        return $this->fiasRepository->findStreets($settlementId);
    }

    /**
     * Получить дочерние записи
     */
    public function getChildren(int $parentId, ?int $level = null): array
    {
        return $this->fiasRepository->findChildren($parentId, $level);
    }

    /**
     * Получить полный путь адреса
     */
    public function getFullAddressPath(Fias $fias): string
    {
        $path = $this->fiasRepository->getFullPath($fias);

        return implode(', ', array_map(
            fn(Fias $item) => $item->getFullAddress(),
            $path
        ));
    }

    /**
     * Найти адрес по ID
     */
    public function findById(int $id): ?Fias
    {
        return $this->fiasRepository->find($id);
    }

    /**
     * Поиск адресов с фильтрами
     */
    public function searchAddresses(
        ?string $name = null,
        ?string $postalcode = null,
        ?int $level = null,
        ?int $parentId = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $qb = $this->fiasRepository->createQueryBuilder('f')
            ->orderBy('f.offname', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($name !== null) {
            $qb->andWhere('f.offname LIKE :name')
               ->setParameter('name', '%' . $name . '%');
        }

        if ($postalcode !== null) {
            $qb->andWhere('f.postalcode = :postalcode')
               ->setParameter('postalcode', $postalcode);
        }

        if ($level !== null) {
            $qb->andWhere('f.level = :level')
               ->setParameter('level', $level);
        }

        if ($parentId !== null) {
            $qb->andWhere('f.parentId = :parentId')
               ->setParameter('parentId', $parentId);
        }

        return $qb->getQuery()->getResult();
    }
}
