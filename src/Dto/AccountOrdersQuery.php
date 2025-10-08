<?php
declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\HttpFoundation\Request;

final class AccountOrdersQuery
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const MIN_LIMIT = 1;

    public function __construct(
        private int $page,
        private int $limit,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', self::DEFAULT_LIMIT);

        $page = $page > 0 ? $page : 1;
        $limit = $limit >= self::MIN_LIMIT ? $limit : self::DEFAULT_LIMIT;
        $limit = $limit <= self::MAX_LIMIT ? $limit : self::MAX_LIMIT;

        return new self($page, $limit);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}

