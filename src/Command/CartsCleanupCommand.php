<?php
declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:carts:cleanup')]
final class CartsCleanupCommand extends Command
{
	public function __construct(private EntityManagerInterface $em) { parent::__construct(); }

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$conn = $this->em->getConnection();
		$conn->executeStatement('DELETE FROM cart_item WHERE cart_id IN (SELECT id FROM cart WHERE expires_at IS NOT NULL AND expires_at < NOW())');
		$conn->executeStatement('DELETE FROM cart WHERE expires_at IS NOT NULL AND expires_at < NOW()');
		$output->writeln('Old carts cleaned');
		return Command::SUCCESS;
	}
}


