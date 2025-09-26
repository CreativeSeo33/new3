<?php

namespace App\Twig;

use App\Repository\DeliveryTypeRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class DeliveryTypeExtension extends AbstractExtension
{
    public function __construct(
        private readonly DeliveryTypeRepository $deliveryTypes,
    ) {}

    private ?array $codeToNameMap = null;

    public function getFunctions(): array
    {
        return [
            new TwigFunction('delivery_type_name', [$this, 'getDeliveryTypeName']),
        ];
    }

    public function getDeliveryTypeName(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        if ($this->codeToNameMap === null) {
            $this->buildMap();
        }

        $normalized = mb_strtolower(trim($code));
        $name = $this->codeToNameMap[$normalized] ?? null;

        return (is_string($name) && $name !== '') ? $name : '—';
    }

    private function buildMap(): void
    {
        $this->codeToNameMap = [];
        foreach ($this->deliveryTypes->findAll() as $type) {
            $code = method_exists($type, 'getCode') ? $type->getCode() : null;
            $name = method_exists($type, 'getName') ? $type->getName() : null;

            if (is_string($code) && $code !== '' && is_string($name) && $name !== '') {
                $this->codeToNameMap[mb_strtolower(trim($code))] = $name;
            }
        }
    }
}


