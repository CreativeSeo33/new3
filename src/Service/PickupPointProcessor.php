<?php
declare(strict_types=1);

namespace App\Service;

use App\Dto\PickupPointDto;
use App\Entity\PvzPoints;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PickupPointProcessor
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<PickupPointDto> $pickupPoints
     */
    public function savePickupPoints(array $pickupPoints): void
    {
        if ($pickupPoints === []) {
            $this->logger->warning('No pickup points to save');
            return;
        }

        $this->logger->info('Saving pickup points', ['count' => count($pickupPoints)]);

        // Очистим существующие записи для полной ресинхронизации
        $this->em->createQuery('DELETE FROM App\\Entity\\PvzPoints p')->execute();

        $batch = 0;
        foreach ($pickupPoints as $dto) {
            $pvz = $this->mapToEntity($dto);
            $this->em->persist($pvz);
            $batch++;
            if (($batch % self::BATCH_SIZE) === 0) {
                $this->flushBatch();
            }
        }

        if (($batch % self::BATCH_SIZE) !== 0) {
            $this->em->flush();
        }

        $this->logger->info('Pickup points saved', ['total_saved' => $batch]);
    }

    private function mapToEntity(PickupPointDto $dto): PvzPoints
    {
        $e = new PvzPoints();
        $e->setCode($dto->code);
        $e->setName($dto->name);
        $e->setAddress($dto->address);
        $e->setCityCode($dto->cityCode);
        $e->setCity($dto->city);
        $e->setRegion($dto->region);
        $e->setPostal($dto->postal);
        $e->setPhone($dto->phone);
        $e->setShirota($dto->latitude);
        $e->setDolgota($dto->longitude);
        $e->setCard(1);
        return $e;
    }

    private function flushBatch(): void
    {
        $this->em->flush();
        $this->em->clear();
    }
}


