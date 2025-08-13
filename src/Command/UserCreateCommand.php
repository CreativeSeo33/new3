<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Создать пользователя с хэшированным паролем')]
final class UserCreateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Имя пользователя (логин)')
            ->addArgument('password', InputArgument::REQUIRED, 'Пароль (будет захэширован)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Выдать роль ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $plainPassword = (string) $input->getArgument('password');
        $makeAdmin = (bool) $input->getOption('admin');

        $userRepo = $this->entityManager->getRepository(User::class);
        $existing = $userRepo->findOneBy(['name' => $name]);
        if ($existing instanceof User) {
            $output->writeln('<error>Пользователь с таким name уже существует</error>');
            return Command::FAILURE;
        }

        $user = (new User())
            ->setName($name)
            ->setRoles($makeAdmin ? ['ROLE_ADMIN'] : []);

        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(sprintf('<info>Создан пользователь %s%s</info>', $name, $makeAdmin ? ' (ROLE_ADMIN)' : ''));
        return Command::SUCCESS;
    }
}


