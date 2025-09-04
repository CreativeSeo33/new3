<?php
declare(strict_types=1);

namespace App\Event;

final class CartUpdatedEvent
{
	public function __construct(public readonly string $cartId) {}
}


