<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class PriceExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('format_price', [$this, 'formatPrice']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_price', [$this, 'formatPrice']),
        ];
    }

    public function formatPrice(int $price, string $currency = '₽'): string
    {
        return number_format($price, 0, '.', ' ') . ' ' . $currency;
    }
}
