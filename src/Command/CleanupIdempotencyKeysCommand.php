<?php
declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-idempotency-keys',
    description: 'Удаляет истекшие ключи идемпотентности',
)]
final class CleanupIdempotencyKeysCommand extends Command
{
    public function __construct(
        private Connection $db,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Размер пачки для удаления',
                1000
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Показать количество записей для удаления без их удаления'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Запуск в режиме dry-run - записи не будут удалены');
        }

        $totalDeleted = 0;

        while (true) {
            try {
                if ($dryRun) {
                    // В dry-run режиме подсчитываем записи
                    $count = $this->db->fetchOne(
                        'SELECT COUNT(*) FROM cart_idempotency WHERE expires_at < UTC_TIMESTAMP(3)',
                        []
                    );

                    $io->success("Найдено {$count} истекших записей для удаления");
                    break;
                }

                // Удаляем пачками
                $affected = $this->db->executeStatement(
                    'DELETE FROM cart_idempotency WHERE expires_at < UTC_TIMESTAMP(3) LIMIT ' . (int)$batchSize
                );

                $totalDeleted += $affected;

                if ($affected === 0) {
                    // Больше нет записей для удаления
                    break;
                }

                $io->writeln("Удалено {$affected} записей");

                // Небольшая пауза между пачками
                usleep(10000); // 10ms

            } catch (\Exception $e) {
                $io->error("Ошибка при очистке: {$e->getMessage()}");
                return Command::FAILURE;
            }
        }

        if (!$dryRun) {
            $io->success("Удалено всего: {$totalDeleted} истекших записей идемпотентности");
        }

        return Command::SUCCESS;
    }
}
