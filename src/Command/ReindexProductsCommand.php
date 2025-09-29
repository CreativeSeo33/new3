<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\Search\ProductIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:search:reindex-products', description: 'Rebuild TNTSearch product index')]
final class ReindexProductsCommand extends Command
{
    public function __construct(private readonly ProductIndexer $indexer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $start = microtime(true);
        $count = $this->indexer->reindexAll();
        $sec = microtime(true) - $start;
        $io->success(sprintf('Reindexed %d products in %.2f sec', $count, $sec));
        return Command::SUCCESS;
    }
}


