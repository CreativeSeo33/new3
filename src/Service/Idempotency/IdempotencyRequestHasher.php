<?php
declare(strict_types=1);

namespace App\Service\Idempotency;

use Symfony\Component\HttpFoundation\Request;

final class IdempotencyRequestHasher
{
    /**
     * AI-META v1
     * role: Канонизация запроса и вычисление стабильного хеша для идемпотентности
     * module: Cart
     * dependsOn:
     *   - Symfony\Component\HttpFoundation\Request
     * invariants:
     *   - Стабильная канонизация JSON тела и маршрутных параметров
     *   - Списки сортируются адресно (только для известных полей), ключи упорядочены
     * transaction: none
     * lastUpdated: 2025-09-15
     */
    public function build(Request $r, array $routeParams = []): array
    {
        $method = strtoupper($r->getMethod());
        $path = explode('?', $r->getRequestUri(), 2)[0];

        $body = $this->parseJsonBody($r);
        $canonical = $this->canonicalize($method, $path, $body, $routeParams);
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $json);

        return [
            'endpoint' => sprintf('%s %s', $method, $path),
            'requestHash' => $hash,
        ];
    }

    private function parseJsonBody(Request $r): mixed
    {
        $content = $r->getContent() ?: '';
        if ($content === '') return null;
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function canonicalize(string $method, string $path, ?array $body, array $route): array
    {
        $obj = ['method' => $method, 'path' => $path, 'body' => new \stdClass(), 'route' => $route];

        $normalize = function(mixed $v) use (&$normalize) {
            if (is_array($v)) {
                $isList = array_is_list($v);
                if ($isList) {
                    // сортировка списков только для известных полей
                    return array_map($normalize, $v);
                }
                ksort($v);
                foreach ($v as $k => $vv) $v[$k] = $normalize($vv);
                return $v;
            }
            if (is_numeric($v) && (string)(int)$v === (string)$v) return (int)$v;
            if (is_numeric($v) && (string)(float)$v === (string)$v) return (float)$v;
            if ($v === '' || $v === null) return null;
            return $v;
        };

        // Политики по эндпоинтам
        $b = $body ?? [];
        if ($method === 'POST' && str_starts_with($path, '/api/cart/items')) {
            $b['optionAssignmentIds'] = array_values(array_map('intval', $b['optionAssignmentIds'] ?? []));
            sort($b['optionAssignmentIds']);
        }
        if ($method === 'POST' && $path === '/api/cart/batch') {
            // Можно отсортировать ключи внутри операций для стабильности
            // порядок операций сохраняем
        }

        $obj['body'] = $normalize($b);
        ksort($obj['route']);

        return $obj;
    }
}
