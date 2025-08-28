<?php
declare(strict_types=1);

namespace App\Command;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:images:cache:warmup')]
final class ImagesCacheWarmupCommand extends Command
{
    private const FILTERS = ['sm', 'md', 'md2', 'xl'];
    private const DEFAULT_BATCH_SIZE = 50;
    private const DEFAULT_PARALLEL_PROCESSES = 4;
    private const LOG_FILE = 'var/log/image_cache_warmup.log';

    private array $stats = [
        'processed' => 0,
        'errors' => 0,
        'skipped' => 0,
        'start_time' => 0,
        'batches_processed' => 0,
    ];

    private bool $shouldStop = false;

    public function __construct(
        private CacheManager $cacheManager,
        private ParameterBagInterface $parameterBag
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Warm up image cache for all filter sets (optimized for 10k+ images)')
            ->addOption('batch-size', 'b', InputOption::VALUE_OPTIONAL, 'Number of images to process per batch', self::DEFAULT_BATCH_SIZE)
            ->addOption('parallel', 'p', InputOption::VALUE_OPTIONAL, 'Number of parallel processes', self::DEFAULT_PARALLEL_PROCESSES)
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would be processed without actually doing it')
            ->addOption('continue', 'c', InputOption::VALUE_NONE, 'Continue from last processed image')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specific filters to process (default: all)', self::FILTERS)
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Specific path to process (default: public/img)')
            ->addOption('detailed', null, InputOption::VALUE_NONE, 'Detailed output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->stats['start_time'] = time();

        // Обработка опций
        $batchSize = (int) $input->getOption('batch-size');
        $parallelProcesses = (int) $input->getOption('parallel');
        $isDryRun = $input->getOption('dry-run');
        $shouldContinue = $input->getOption('continue');
        $filters = $input->getOption('filter');
        $customPath = $input->getOption('path');
        $isDetailed = $input->getOption('detailed');

        if ($isDryRun) {
            $output->writeln('🔍 <comment>DRY RUN MODE - No actual processing will be done</comment>');
        }

        // Настройка сигналов для graceful shutdown
        $this->setupSignalHandlers();

        // Поиск изображений
        $images = $this->findImages($customPath);
        $totalImages = count($images);

        if ($totalImages === 0) {
            $output->writeln('❌ Изображения не найдены');
            return Command::FAILURE;
        }

        // Фильтрация изображений если нужно продолжить
        if ($shouldContinue) {
            $images = $this->filterProcessedImages($images);
            $totalImages = count($images);
        }

        $totalOperations = $totalImages * count($filters);
        $totalBatches = (int) ceil($totalImages / $batchSize);

        $this->displayStartInfo($output, $totalImages, $totalOperations, $totalBatches, $batchSize, $parallelProcesses, $filters, $isDryRun);

        if ($isDryRun) {
            $this->displayDryRunInfo($output, array_slice($images, 0, 5), $filters);
            return Command::SUCCESS;
        }

        // Создание директории для логов
        $this->ensureLogDirectory();

        // Основной цикл обработки
        $progressBar = new ProgressBar($output, $totalOperations);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        try {
            $batches = array_chunk($images, $batchSize);

            foreach ($batches as $batchIndex => $batch) {
                if ($this->shouldStop) {
                    $output->writeln("\n⚠️  Получен сигнал прерывания. Сохранение прогресса...");
                    break;
                }

                $this->processBatch($batch, $filters, $parallelProcesses, $progressBar, $output, $isDetailed);
                $this->stats['batches_processed']++;

                // Очистка памяти
                if ($batchIndex % 10 === 0) {
                    gc_collect_cycles();
                }

                // Сохранение прогресса каждые 100 батчей
                if ($batchIndex % 100 === 0) {
                    $this->saveProgress($batchIndex, $batch);
                }
            }

            $progressBar->finish();
            $this->displayFinalStats($output);

        } catch (\Exception $e) {
            $progressBar->finish();
            $output->writeln("\n❌ Критическая ошибка: " . $e->getMessage());
            $this->logError('Critical error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function findImages(?string $customPath = null): array
    {
        $searchPath = $customPath ?: $this->parameterBag->get('kernel.project_dir') . '/public/img';

        if (!is_dir($searchPath)) {
            throw new \InvalidArgumentException("Directory does not exist: {$searchPath}");
        }

        $finder = new Finder();
        $finder->files()
            ->in($searchPath)
            ->name(['*.jpg', '*.jpeg', '*.png', '*.gif', '*.webp'])
            ->notName(['.*'])
            ->size('> 0') // Только файлы с размером > 0
            ->sortByName();

        $images = [];
        foreach ($finder as $file) {
            $images[] = $file->getPathname();
        }

        return $images;
    }

    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
        }
    }

    private function signalHandler(int $signal): void
    {
        $this->shouldStop = true;
        $this->logMessage("Received signal {$signal}, stopping gracefully...");
    }

    private function displayStartInfo(OutputInterface $output, int $totalImages, int $totalOperations, int $totalBatches, int $batchSize, int $parallelProcesses, array $filters, bool $isDryRun): void
    {
        $output->writeln("📸 Найдено изображений: <info>{$totalImages}</info>");
        $output->writeln("🔄 Всего операций: <info>{$totalOperations}</info>");
        $output->writeln("📦 Батчей: <info>{$totalBatches}</info> (по {$batchSize} изображений)");
        $output->writeln("⚡ Параллельных процессов: <info>{$parallelProcesses}</info>");
        $output->writeln("🎯 Фильтры: <info>" . implode(', ', $filters) . "</info>");
        $output->writeln("");

        if ($isDryRun) {
            $output->writeln("🔍 <comment>DRY RUN - Показать что будет обработано</comment>");
        }
    }

    private function displayDryRunInfo(OutputInterface $output, array $sampleImages, array $filters): void
    {
        $output->writeln("\n📋 <comment>Примеры изображений для обработки:</comment>");
        foreach (array_slice($sampleImages, 0, 5) as $image) {
            $relativePath = str_replace($this->parameterBag->get('kernel.project_dir') . '/public/', '', $image);
            $output->writeln("  📄 {$relativePath}");
        }

        $output->writeln("\n🎯 <comment>Фильтры для применения:</comment>");
        foreach ($filters as $filter) {
            $output->writeln("  🔧 {$filter}");
        }
    }

    private function ensureLogDirectory(): void
    {
        $logDir = dirname($this->parameterBag->get('kernel.project_dir') . '/' . self::LOG_FILE);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    private function processBatch(array $batch, array $filters, int $parallelProcesses, ProgressBar $progressBar, OutputInterface $output, bool $isDetailed): void
    {
        $runningProcesses = [];

        foreach ($batch as $imagePath) {
            $relativePath = str_replace($this->parameterBag->get('kernel.project_dir') . '/public/', '', $imagePath);

            // Ожидание если достигнуто максимальное количество параллельных процессов
            while (count($runningProcesses) >= $parallelProcesses) {
                $this->checkRunningProcesses($runningProcesses, $progressBar, $output, $isDetailed);
                usleep(10000); // 10ms
            }

            // Запуск процессов для каждого фильтра
            foreach ($filters as $filter) {
                $process = new Process([
                    'php',
                    'bin/console',
                    'liip:imagine:cache:resolve',
                    $relativePath,
                    '--filter=' . $filter,
                    '--force'
                ]);

                $process->setWorkingDirectory($this->parameterBag->get('kernel.project_dir'));
                $process->start();

                $runningProcesses[] = [
                    'process' => $process,
                    'image' => $relativePath,
                    'filter' => $filter,
                    'start_time' => time()
                ];
            }
        }

        // Дождаться завершения всех процессов в батче
        while (!empty($runningProcesses)) {
            $this->checkRunningProcesses($runningProcesses, $progressBar, $output, $isDetailed);
            usleep(10000); // 10ms
        }
    }

    private function checkRunningProcesses(array &$runningProcesses, ProgressBar $progressBar, OutputInterface $output, bool $isDetailed): void
    {
        foreach ($runningProcesses as $key => $processInfo) {
            $process = $processInfo['process'];

            if (!$process->isRunning()) {
                if ($process->isSuccessful()) {
                    $this->stats['processed']++;
                    if ($isDetailed) {
                        $output->writeln("✅ {$processInfo['image']} [{$processInfo['filter']}]");
                    }
                } else {
                    $this->stats['errors']++;
                    $errorMsg = $process->getErrorOutput();
                    $this->logError("Failed: {$processInfo['image']} [{$processInfo['filter']}] - {$errorMsg}");

                    if ($isDetailed) {
                        $output->writeln("❌ {$processInfo['image']} [{$processInfo['filter']}] - {$errorMsg}");
                    }
                }

                $progressBar->advance();
                unset($runningProcesses[$key]);

                // Проверка таймаута (5 минут)
                $elapsed = time() - $processInfo['start_time'];
                if ($elapsed > 300) {
                    $this->logMessage("Process timeout: {$processInfo['image']} [{$processInfo['filter']}] ({$elapsed}s)");
                }
            }
        }
    }

    private function logMessage(string $message): void
    {
        $logFile = $this->parameterBag->get('kernel.project_dir') . '/' . self::LOG_FILE;
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }

    private function logError(string $message): void
    {
        $this->logMessage("ERROR: {$message}");
    }

    private function saveProgress(int $batchIndex, array $batch): void
    {
        $progressFile = $this->parameterBag->get('kernel.project_dir') . '/var/cache/image_cache_progress.json';
        $data = [
            'last_batch_index' => $batchIndex,
            'last_processed_image' => end($batch),
            'stats' => $this->stats,
            'timestamp' => time()
        ];

        file_put_contents($progressFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function filterProcessedImages(array $images): array
    {
        $progressFile = $this->parameterBag->get('kernel.project_dir') . '/var/cache/image_cache_progress.json';

        if (!file_exists($progressFile)) {
            return $images;
        }

        $progress = json_decode(file_get_contents($progressFile), true);
        $lastProcessedImage = $progress['last_processed_image'] ?? null;

        if (!$lastProcessedImage) {
            return $images;
        }

        $startIndex = array_search($lastProcessedImage, $images);
        if ($startIndex === false) {
            return $images;
        }

        return array_slice($images, $startIndex + 1);
    }

    private function displayFinalStats(OutputInterface $output): void
    {
        $endTime = time();
        $duration = $endTime - $this->stats['start_time'];
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        $output->writeln('');
        $output->writeln('📊 <info>Финальная статистика:</info>');
        $output->writeln("   ✅ Обработано: <info>{$this->stats['processed']}</info>");
        $output->writeln("   ❌ Ошибок: <error>{$this->stats['errors']}</error>");
        $output->writeln("   ⏭️  Пропущено: <comment>{$this->stats['skipped']}</comment>");
        $output->writeln("   📦 Батчей: <info>{$this->stats['batches_processed']}</info>");
        $output->writeln("   ⏱️  Время выполнения: <info>{$hours}ч {$minutes}м {$seconds}с</info>");

        $avgTimePerOperation = $duration > 0 ? $duration / $this->stats['processed'] : 0;
        $output->writeln("   📈 Среднее время на операцию: <info>" . number_format($avgTimePerOperation, 2) . "с</info>");

        $this->logMessage("Completed: {$this->stats['processed']} processed, {$this->stats['errors']} errors");
    }
}
