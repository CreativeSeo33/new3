<?php
declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AttributeGroup;
use App\Repository\AttributeRepository;
use App\Repository\ProductAttributeGroupRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteAttributeGroupRestrictProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeRepository $attributeRepo,
        private readonly ProductAttributeGroupRepository $productAttrGroupRepo,
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

        $productGroupsCount = (int) $this->productAttrGroupRepo->createQueryBuilder('pag')
            ->select('COUNT(pag.id)')
            ->andWhere('pag.attributeGroup = :grp')
            ->setParameter('grp', $group)
            ->getQuery()
            ->getSingleScalarResult();

        if ($attributesCount > 0 || $productGroupsCount > 0) {
            throw new ConflictHttpException(sprintf(
                'Нельзя удалить группу атрибутов "%s": %d атрибут(ов), %d привязок к товарам. Сначала очистите связи.',
                (string) $group->getName(),
                $attributesCount,
                $productGroupsCount
            ));
        }

        $this->em->remove($group);
        $this->em->flush();
        return null;
    }
}


