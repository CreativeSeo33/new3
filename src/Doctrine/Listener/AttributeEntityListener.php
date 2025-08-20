<?php
declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Attribute;
use App\Repository\AttributeRepository;
use App\Service\AttributeCodeGenerator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: Attribute::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Attribute::class)]
final class AttributeEntityListener
{
    public function __construct(
        private readonly AttributeRepository $repo,
        private readonly AttributeCodeGenerator $generator
    ) {}

    public function prePersist(Attribute $attribute, PrePersistEventArgs $event): void
    {
        $code = $attribute->getCode() ?? '';
        if ($code === '') {
            $base = $this->generator->baseFromName($attribute->getName() ?? 'attribute');
            $unique = $this->generator->makeUnique($base, fn(string $c) => $this->repo->codeExists($c));
            $attribute->setCode($unique);
            return;
        }

        $normalized = $this->generator->normalizeInput($code);
        $attribute->setCode($normalized);
    }

    public function preUpdate(Attribute $attribute, PreUpdateEventArgs $event): void
    {
        $code = $attribute->getCode() ?? '';
        if ($code !== '') {
            $attribute->setCode($this->generator->normalizeInput($code));
        }

        $om = $event->getObjectManager();
        if ($om instanceof EntityManagerInterface) {
            $meta = $om->getClassMetadata(Attribute::class);
            $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $attribute);
        }
    }
}





