<?php
declare(strict_types=1);

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Attribute;
use App\Repository\ProductAttributeRepository;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteAttributeRestrictProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductAttributeRepository $productAttributeRepo,
    ) {}

    /** @param Attribute $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $attr = $data;

        $usedCount = (int) $this->productAttributeRepo->createQueryBuilder('pa')
            ->select('COUNT(pa.id)')
            ->andWhere('pa.attribute = :attr')
            ->setParameter('attr', $attr)
            ->getQuery()
            ->getSingleScalarResult();

        if ($usedCount > 0) {
            throw new ConflictHttpException(sprintf(
                'Нельзя удалить атрибут "%s": используется в %d значениях товаров. Сначала удалите/перенесите использования.',
                (string) $attr->getName(),
                $usedCount
            ));
        }

        $this->em->remove($attr);
        $this->em->flush();
        return null;
    }
}


