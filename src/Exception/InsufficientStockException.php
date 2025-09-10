<?php
declare(strict_types=1);

namespace App\Exception;

final class InsufficientStockException extends \DomainException
{
    private int $availableQuantity;

    public function __construct(
        string $message,
        int $availableQuantity,
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->availableQuantity = $availableQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->availableQuantity;
    }
}
