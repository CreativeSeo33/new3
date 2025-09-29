<?php
declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\OpenApi;

final class ProductOpenApiDecorator implements OpenApiFactoryInterface
{
    public function __construct(private readonly OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $path => $item) {
            if (!str_contains($path, '/v2/products')) { continue; }
            $op = $item->getGet();
            if ($op === null) { continue; }
            $parameters = $op->getParameters() ?? [];
            $parameters[] = new Model\Parameter('q', 'query', 'Строка поиска (морфологический, TNTSearch). Пример: "красный телефон"', false, 'string');
            $op = $op->withParameters($parameters);
            $paths->addPath($path, $item->withGet($op));
        }

        return $openApi->withPaths($paths);
    }
}


