<?php
declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\State\ProductStateProcessor;
use App\State\ProductStateProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'Product',
    routePrefix: '/v2',
    operations: [
        new Get(provider: ProductStateProvider::class),
        new GetCollection(provider: ProductStateProvider::class),
        new Post(processor: ProductStateProcessor::class),
        new Patch(processor: ProductStateProcessor::class, read: false),
        new Delete(processor: ProductStateProcessor::class, read: false, output: false)
    ],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']]
)]
class ProductResource
{
    #[Groups(['product:read'])]
    public ?int $id = null;

    #[Groups(['product:read'])]
    public ?string $code = null;

    #[Groups(['product:read', 'product:write'])]
    public ?string $name = null;

    #[Groups(['product:read', 'product:write'])]
    public ?string $slug = null;

    #[Groups(['product:read', 'product:write'])]
    public ?int $price = null;

    #[Groups(['product:read', 'product:write'])]
    public ?int $salePrice = null;

    #[Groups(['product:read'])]
    public ?int $effectivePrice = null;

    #[Groups(['product:read', 'product:write'])]
    public ?bool $status = null;

    #[Groups(['product:read', 'product:write'])]
    public ?int $quantity = null;

    #[Groups(['product:read', 'product:write'])]
    public ?int $sortOrder = null;

    #[Groups(['product:read', 'product:write'])]
    public ?string $description = null;

    // SEO
    #[Groups(['product:read', 'product:write'])]
    public ?string $metaTitle = null;

    #[Groups(['product:read', 'product:write'])]
    public ?string $metaDescription = null;

    #[Groups(['product:read', 'product:write'])]
    public ?string $metaKeywords = null;

    #[Groups(['product:read', 'product:write'])]
    public ?string $h1 = null;

    // Manufacturer
    #[Groups(['product:read', 'product:write'])]
    public ?int $manufacturerId = null;

    #[Groups(['product:read'])]
    public ?string $manufacturerName = null;
}


