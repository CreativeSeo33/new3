<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Cart;
use App\Entity\CartItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-remove-direct',
    description: 'Test removing cart item directly'
)]
final class TestRemoveDirectCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('itemId', InputArgument::REQUIRED, 'Cart item ID to remove');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $itemId = (int) $input->getArgument('itemId');

        $output->writeln("Testing direct removal of cart item ID: {$itemId}");

        // Находим товар напрямую
        $item = $this->em->getRepository(CartItem::class)->find($itemId);

        if (!$item) {
            $output->writeln("❌ Item not found");
            return Command::FAILURE;
        }

        $cart = $item->getCart();
        $output->writeln("Found item in cart: " . ($cart ? $cart->getIdString() : 'null'));

        // Удаляем товар
        $this->em->remove($item);
        $this->em->flush();

        $output->writeln("✅ Item removed successfully");

        // Проверяем, удален ли товар
        $count = $this->em->getConnection()->executeQuery(
            "SELECT COUNT(*) FROM cart_item WHERE id = ?",
            [$itemId]
        )->fetchOne();

        $output->writeln("Items remaining with ID {$itemId}: {$count}");

        return $count == 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
