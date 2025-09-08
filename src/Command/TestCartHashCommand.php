<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\CartManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-cart-hash',
    description: 'Test cart options hash generation'
)]
final class TestCartHashCommand extends Command
{
    public function __construct(
        private CartManager $cartManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🧪 Testing cart options hash generation...');

        // Test empty options - should return ''
        $hash1 = $this->cartManager->generateOptionsHash([]);
        $output->writeln("Empty options hash: '{$hash1}'");
        $expectedEmpty = ($hash1 === '') ? '✅ CORRECT' : '❌ WRONG';
        $output->writeln("Expected empty string: {$expectedEmpty}");

        // Test with options
        $hash2 = $this->cartManager->generateOptionsHash([1, 2, 3]);
        $output->writeln("Options [1,2,3] hash: '{$hash2}'");
        $isMd5 = (strlen($hash2) === 32 && ctype_xdigit($hash2)) ? '✅ MD5' : '❌ NOT MD5';
        $output->writeln("Is valid MD5: {$isMd5}");

        // Test order independence
        $hash3 = $this->cartManager->generateOptionsHash([3, 2, 1]);
        $output->writeln("Options [3,2,1] hash: '{$hash3}'");
        $orderIndependent = ($hash2 === $hash3) ? '✅ ORDER INDEPENDENT' : '❌ ORDER DEPENDENT';
        $output->writeln("Order independence: {$orderIndependent}");

        // Test deduplication
        $hash4 = $this->cartManager->generateOptionsHash([1, 2, 2, 3, 1]);
        $output->writeln("Options [1,2,2,3,1] hash: '{$hash4}'");
        $deduplicated = ($hash2 === $hash4) ? '✅ DEDUPLICATED' : '❌ NOT DEDUPLICATED';
        $output->writeln("Deduplication: {$deduplicated}");

        $output->writeln('');
        $output->writeln('🎯 Summary:');
        $output->writeln('- Empty options → empty string: ' . (($hash1 === '') ? '✅' : '❌'));
        $output->writeln('- Order independence: ' . (($hash2 === $hash3) ? '✅' : '❌'));
        $output->writeln('- Deduplication: ' . (($hash2 === $hash4) ? '✅' : '❌'));
        $output->writeln('- Valid MD5 format: ' . ((strlen($hash2) === 32 && ctype_xdigit($hash2)) ? '✅' : '❌'));

        return Command::SUCCESS;
    }
}
