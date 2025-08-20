<?php
declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Attribute;
use App\Repository\ProductAttributeAssignmentRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteAttributeRestrictProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductAttributeAssignmentRepository $assignmentRepo,
    ) {}

    /** @param Attribute $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $attr = $data;

        $assignCount = (int) $this->assignmentRepo->createQueryBuilder('aa')
            ->select('COUNT(aa.id)')
            ->andWhere('aa.attribute = :attr')
            ->setParameter('attr', $attr)
            ->getQuery()
            ->getSingleScalarResult();

        if ($assignCount > 0) {
            throw new ConflictHttpException(sprintf(
                'Нельзя удалить атрибут "%s": используется в %d назначениях. Сначала удалите/перенесите использования.',
                (string) $attr->getName(),
                $assignCount
            ));
        }

        $this->em->remove($attr);
        $this->em->flush();
        return null;
    }
}


