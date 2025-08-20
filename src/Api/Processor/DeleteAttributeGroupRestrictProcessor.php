<?php
declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AttributeGroup;
use App\Repository\AttributeRepository;
use App\Repository\ProductAttributeAssignmentRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteAttributeGroupRestrictProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeRepository $attributeRepo,
        private readonly ProductAttributeAssignmentRepository $assignmentRepo,
    ) {}

    /** @param AttributeGroup $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $group = $data;

        $attributesCount = (int) $this->attributeRepo->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.attributeGroup = :grp')
            ->setParameter('grp', $group)
            ->getQuery()
            ->getSingleScalarResult();

        $assignCount = (int) $this->assignmentRepo->createQueryBuilder('aa')
            ->select('COUNT(aa.id)')
            ->andWhere('aa.attributeGroup = :grp')
            ->setParameter('grp', $group)
            ->getQuery()
            ->getSingleScalarResult();

        if ($attributesCount > 0 || $assignCount > 0) {
            throw new ConflictHttpException(sprintf(
                'Нельзя удалить группу атрибутов "%s": %d атрибут(ов), %d назначений. Сначала очистите связи.',
                (string) $group->getName(),
                $attributesCount,
                $assignCount
            ));
        }

        $this->em->remove($group);
        $this->em->flush();
        return null;
    }
}


