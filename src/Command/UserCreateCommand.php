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
            ->addArgument('email', InputArgument::REQUIRED, 'Email пользователя')
            ->addArgument('password', InputArgument::REQUIRED, 'Пароль (будет захэширован)')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Выдать роль ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $plainPassword = (string) $input->getArgument('password');
        $makeAdmin = (bool) $input->getOption('admin');

        $userRepo = $this->entityManager->getRepository(User::class);
        $existing = $userRepo->findOneBy(['email' => mb_strtolower($email)]);
        if ($existing instanceof User) {
            $output->writeln('<error>Пользователь с таким email уже существует</error>');
            return Command::FAILURE;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles($makeAdmin ? ['ROLE_ADMIN'] : []);

        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(sprintf('<info>Создан пользователь %s%s</info>', $email, $makeAdmin ? ' (ROLE_ADMIN)' : ''));
        return Command::SUCCESS;
    }
}


