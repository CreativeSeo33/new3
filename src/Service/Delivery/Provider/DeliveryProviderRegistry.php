<?php
declare(strict_types=1);

namespace App\Service\Delivery\Provider;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class DeliveryProviderRegistry
{
    /** @var array<string, DeliveryProviderInterface> */
    private array $map = [];

    public function __construct(#[TaggedIterator('app.delivery_provider')] iterable $providers)
    {
        foreach ($providers as $p) {
            $this->map[$p->getCode()] = $p;
        }
    }

    public function get(string $code): ?DeliveryProviderInterface
    {
        return $this->map[$code] ?? null;
    }

    /** @return array<string, DeliveryProviderInterface> */
    public function all(): array
    {
        return $this->map;
    }
}
