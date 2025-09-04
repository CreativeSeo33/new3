<?php
declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:session:clear',
    description: 'Clear session data (all or specific keys)',
)]
final class SessionClearCommand extends Command
{
    public function __construct(
        private RequestStack $requestStack,
        private Filesystem $filesystem,
        private string $sessionSavePath = ''
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'key',
                'k',
                InputOption::VALUE_OPTIONAL,
                'Clear specific session key (leave empty to clear all)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force clear without confirmation'
            )
            ->setHelp('This command clears session data in the application.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specificKey = $input->getOption('key');
        $force = $input->getOption('force');

        // Проверяем, доступна ли сессия (работаем в веб-контексте)
        if ($this->requestStack->getCurrentRequest()) {
            return $this->clearActiveSession($io, $specificKey, $force);
        }

        // Работаем с файлами сессий (CLI контекст)
        return $this->clearSessionFiles($io, $force);
    }

    private function clearActiveSession(SymfonyStyle $io, ?string $specificKey, bool $force): int
    {
        $session = $this->requestStack->getSession();

        if ($specificKey) {
            // Очистка конкретного ключа
            if (!$force) {
                $confirm = $io->confirm(
                    sprintf('Are you sure you want to clear session key "%s"?', $specificKey),
                    false
                );

                if (!$confirm) {
                    $io->note('Operation cancelled.');
                    return Command::SUCCESS;
                }
            }

            if ($session->has($specificKey)) {
                $session->remove($specificKey);
                $session->save();
                $io->success(sprintf('Session key "%s" has been cleared.', $specificKey));
            } else {
                $io->warning(sprintf('Session key "%s" does not exist.', $specificKey));
            }
        } else {
            // Полная очистка сессии
            if (!$force) {
                $confirm = $io->confirm(
                    'Are you sure you want to clear ALL session data? This action cannot be undone.',
                    false
                );

                if (!$confirm) {
                    $io->note('Operation cancelled.');
                    return Command::SUCCESS;
                }
            }

            // Показываем текущее состояние сессии
            $allData = $session->all();
            if (empty($allData)) {
                $io->info('Session is already empty.');
                return Command::SUCCESS;
            }

            $io->section('Current session data:');
            $io->listing(array_keys($allData));

            // Полная очистка
            $session->clear();
            $session->save();

            $io->success('All session data has been cleared.');
        }

        return Command::SUCCESS;
    }

    private function clearSessionFiles(SymfonyStyle $io, bool $force): int
    {
        // Определяем путь к сессиям
        $sessionPath = $this->sessionSavePath ?: 'var/sessions';

        if (!$this->filesystem->exists($sessionPath)) {
            $io->info('Session directory does not exist. Nothing to clear.');
            return Command::SUCCESS;
        }

        // Подсчитываем файлы сессий
        $finder = new Finder();
        $finder->files()->in($sessionPath);

        $fileCount = $finder->count();
        if ($fileCount === 0) {
            $io->info('No session files found.');
            return Command::SUCCESS;
        }

        // Показываем информацию о файлах
        $io->section('Session files found:');
        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRelativePathname() . ' (' . $file->getSize() . ' bytes)';
        }
        $io->listing($files);

        if (!$force) {
            $confirm = $io->confirm(
                sprintf('Are you sure you want to delete %d session file(s)?', $fileCount),
                false
            );

            if (!$confirm) {
                $io->note('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Удаляем файлы
        $this->filesystem->remove($finder);

        $io->success(sprintf('%d session file(s) have been deleted.', $fileCount));
        return Command::SUCCESS;
    }
}
