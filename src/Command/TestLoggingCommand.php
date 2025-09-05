<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LoggerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-logging',
    description: 'Тестовая команда для проверки логирования'
)]
class TestLoggingCommand extends Command
{
    public function __construct(
        private LoggerService $logger
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Test info message from command');
        $this->logger->warning('Test warning message from command');
        $this->logger->error('Test error message from command');
        $this->logger->debug('Test debug message from command');

        $this->logger->logUserAction('test_command_executed', 'system', [
            'command' => 'app:test-logging',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $output->writeln('Логи записаны. Проверьте файлы в var/log/');

        return Command::SUCCESS;
    }
}