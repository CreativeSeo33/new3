<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\CartManager;
use App\Service\CartContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Response;

#[AsCommand(
    name: 'app:test-remove-item',
    description: 'Test removing an item from cart'
)]
final class TestRemoveItemCommand extends Command
{
    public function __construct(
        private CartManager $cartManager,
        private CartContext $cartContext
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

        $output->writeln("Testing removal of cart item ID: {$itemId}");

        // Создаем корзину
        $response = new Response();
        $cart = $this->cartContext->getOrCreate(null, $response);
        $output->writeln("Cart ID: " . $cart->getIdString());
        $output->writeln("Items before removal: " . $cart->getItems()->count());

        // Пытаемся удалить товар
        $result = $this->cartManager->removeItem($cart, $itemId);

        if ($result) {
            $output->writeln("✅ Item removed successfully!");
            $output->writeln("Items after removal: " . $result->getItems()->count());
            return Command::SUCCESS;
        } else {
            $output->writeln("❌ Failed to remove item (item not found or error occurred)");
            return Command::FAILURE;
        }
    }
}
