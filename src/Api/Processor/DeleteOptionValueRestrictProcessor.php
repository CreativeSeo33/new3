<?php
declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\OptionValue;
use App\Repository\ProductOptionValueAssignmentRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteOptionValueRestrictProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductOptionValueAssignmentRepository $assignRepo,
    ) {}

    /** @param OptionValue $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $value = $data;

        $assignCount = (int) $this->assignRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.value = :val')
            ->setParameter('val', $value)
            ->getQuery()->getSingleScalarResult();

        if ($assignCount > 0) {
            throw new ConflictHttpException(sprintf(
                'Нельзя удалить значение "%s" (%s): %d назначений в товарах. Сначала удалите назначения.',
                $value->getValue(),
                $value->getCode(),
                $assignCount
            ));
        }

        $this->em->remove($value);
        $this->em->flush();
        return null;
    }
}


