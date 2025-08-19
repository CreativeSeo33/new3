<?php
declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Option;
use App\Repository\OptionValueRepository;
use App\Repository\ProductOptionValueAssignmentRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteOptionRestrictProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OptionValueRepository $valueRepo,
        private readonly ProductOptionValueAssignmentRepository $assignRepo,
    ) {}

    /** @param Option $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $option = $data;

        $valuesCount = (int) $this->valueRepo->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.optionType = :opt')
            ->setParameter('opt', $option)
            ->getQuery()->getSingleScalarResult();

        $assignCount = (int) $this->assignRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.option = :opt')
            ->setParameter('opt', $option)
            ->getQuery()->getSingleScalarResult();

        if ($valuesCount > 0 || $assignCount > 0) {
            throw new ConflictHttpException(sprintf(
                'Нельзя удалить опцию "%s": %d значений, %d назначений в товарах. Сначала удалите назначения и значения.',
                $option->getName(),
                $valuesCount,
                $assignCount
            ));
        }

        // Выполняем стандартное удаление вручную
        $this->em->remove($option);
        $this->em->flush();
        return null;
    }
}


