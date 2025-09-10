<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Idempotency\IdempotencyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'test:idempotency',
    description: 'Test idempotency functionality',
)]
class TestIdempotencyCommand extends Command
{
    public function __construct(
        private IdempotencyService $idempotencyService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing Idempotency Service...');

        $key = 'test-key-' . time();
        $cartId = 'test-cart-123';
        $endpoint = '/api/cart/items';
        $requestHash = hash('sha256', 'test-payload');
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        try {
            // Test 1: Begin new idempotency operation
            $output->writeln('Test 1: Starting new idempotency operation...');
            $result = $this->idempotencyService->begin($key, $cartId, $endpoint, $requestHash, $nowUtc);

            if ($result->type === 'started') {
                $output->writeln('✅ Successfully started new operation');
            } else {
                $output->writeln('❌ Failed to start operation: ' . $result->type);
                return Command::FAILURE;
            }

            // Test 2: Try to replay the same operation
            $output->writeln('Test 2: Trying to replay the same operation...');
            $result2 = $this->idempotencyService->begin($key, $cartId, $endpoint, $requestHash, $nowUtc);

            if ($result2->type === 'in_flight') {
                $output->writeln('✅ Correctly detected in-flight operation');
            } else {
                $output->writeln('❌ Expected in_flight, got: ' . $result2->type);
                return Command::FAILURE;
            }

            // Test 3: Finish the operation
            $output->writeln('Test 3: Finishing the operation...');
            $this->idempotencyService->finish($key, 200, ['test' => 'response']);
            $output->writeln('✅ Successfully finished operation');

            // Test 4: Try to replay finished operation
            $output->writeln('Test 4: Trying to replay finished operation...');
            $result3 = $this->idempotencyService->begin($key, $cartId, $endpoint, $requestHash, $nowUtc);

            if ($result3->type === 'replay' && $result3->httpStatus === 200) {
                $output->writeln('✅ Correctly replayed finished operation');
            } else {
                $output->writeln('❌ Expected replay with status 200, got: ' . $result3->type);
                return Command::FAILURE;
            }

            // Test 5: Try conflict with different hash
            $output->writeln('Test 5: Testing conflict with different request hash...');
            $differentHash = hash('sha256', 'different-payload');
            $result4 = $this->idempotencyService->begin($key, $cartId, $endpoint, $differentHash, $nowUtc);

            if ($result4->type === 'conflict') {
                $output->writeln('✅ Correctly detected conflict');
            } else {
                $output->writeln('❌ Expected conflict, got: ' . $result4->type);
                return Command::FAILURE;
            }

            $output->writeln('🎉 All tests passed! Idempotency functionality is working correctly.');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln('❌ Test failed with exception: ' . $e->getMessage());
            $output->writeln('Stack trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
