<?php
declare(strict_types=1);

namespace App\Command;

use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cart:purge-expired',
    description: 'Remove expired guest carts from database',
)]
final class CartPurgeExpiredCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private CartRepository $carts,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Purging expired carts');

        // Находим просроченные корзины
        $expiredCarts = $this->carts->createQueryBuilder('c')
            ->andWhere('c.expiresAt IS NOT NULL')
            ->andWhere('c.expiresAt < CURRENT_TIMESTAMP()')
            ->getQuery()
            ->getResult();

        if (empty($expiredCarts)) {
            $io->success('No expired carts found');
            return Command::SUCCESS;
        }

        $count = count($expiredCarts);
        $io->info("Found {$count} expired cart(s) to purge");

        if (!$io->confirm("Are you sure you want to delete {$count} expired cart(s)?", false)) {
            $io->note('Operation cancelled');
            return Command::SUCCESS;
        }

        // Удаляем просроченные корзины
        $deletedCount = 0;
        foreach ($expiredCarts as $cart) {
            $this->em->remove($cart);
            $deletedCount++;
        }

        $this->em->flush();

        $io->success("Successfully deleted {$deletedCount} expired cart(s)");
        return Command::SUCCESS;
    }
}
