<?php
declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

final class UserPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * @param User $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            $plain = $data->getPlainPassword();
            if (is_string($plain) && $plain !== '') {
                $hashed = $this->passwordHasher->hashPassword($data, $plain);
                $data->setPassword($hashed);
                $data->setPlainPassword(null);
            }
            // нормализуем роли: убираем дубликаты и пустые
            $roles = array_values(array_filter(array_map('strval', $data->getRoles())));
            $data->setRoles($roles);
        }

        // Сохраняем напрямую через Doctrine
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}


