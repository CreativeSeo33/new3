<?php
declare(strict_types=1);

namespace App\Doctrine\Listener;

use App\Entity\Option;
use App\Repository\OptionRepository;
use App\Service\OptionCodeGenerator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: Option::class)]
#[AsEntityListener(event: Events::preUpdate, entity: Option::class)]
final class OptionEntityListener
{
    public function __construct(
        private readonly OptionRepository $repo,
        private readonly OptionCodeGenerator $generator
    ) {}

    public function prePersist(Option $option, PrePersistEventArgs $event): void
    {
        $code = $option->getCode() ?? '';
        if ($code === '') {
            $base = $this->generator->baseFromName($option->getName() ?? 'option');
            $unique = $this->generator->makeUnique($base, fn(string $c) => $this->repo->codeExists($c));
            $option->setCode($unique);
            return;
        }

        $normalized = $this->generator->normalizeInput($code);
        $option->setCode($normalized);
    }

    public function preUpdate(Option $option, PreUpdateEventArgs $event): void
    {
        $code = $option->getCode() ?? '';
        if ($code !== '') {
            $option->setCode($this->generator->normalizeInput($code));
        }

        // Обновляем change set, если поле изменилось внутри listener
        $om = $event->getObjectManager();
        if ($om instanceof EntityManagerInterface) {
            $meta = $om->getClassMetadata(Option::class);
            $om->getUnitOfWork()->recomputeSingleEntityChangeSet($meta, $option);
        }
    }
}


