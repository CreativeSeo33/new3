<?php
declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'YandexMap')]
final class YandexMap
{
    public ?string $heightClass = 'h-96';
    /** @var array<int, array{ id?: string|int, lat: float, lon: float, title?: string, address?: string }> */
    public array $points = [];
    public ?string $centerLat = null;
    public ?string $centerLon = null;
    public ?int $zoom = 12;
    public bool $fitBounds = true;
    /** @var array<string, mixed> */
    public array $clusterOptions = [];
    public bool $showList = false;
}


