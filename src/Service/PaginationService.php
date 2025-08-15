<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PaginationService
{
    /** @var int[] */
    private array $allowedItemsPerPage;
    private int $defaultItemsPerPage;

    /** @var int[] */
    private array $cityAllowedItemsPerPage;
    private int $cityDefaultItemsPerPage;

    /** @var int[] */
    private array $pvzAllowedItemsPerPage;
    private int $pvzDefaultItemsPerPage;

    public function __construct(ParameterBagInterface $params)
    {
        $this->allowedItemsPerPage = (array)$params->get('pagination.items_per_page_options');
        $this->defaultItemsPerPage = (int)$params->get('pagination.default_items_per_page');

        $this->cityAllowedItemsPerPage = (array)$params->get('pagination.city.items_per_page_options');
        $this->cityDefaultItemsPerPage = (int)$params->get('pagination.city.default_items_per_page');

        $this->pvzAllowedItemsPerPage = (array)$params->get('pagination.pvz.items_per_page_options');
        $this->pvzDefaultItemsPerPage = (int)$params->get('pagination.pvz.default_items_per_page');
    }

    public function normalizeItemsPerPage(int $itemsPerPage): int
    {
        return in_array($itemsPerPage, $this->allowedItemsPerPage, true)
            ? $itemsPerPage
            : $this->defaultItemsPerPage;
    }

    /** @return int[] */
    public function getAllowedItemsPerPage(): array
    {
        return $this->allowedItemsPerPage;
    }

    public function getDefaultItemsPerPage(): int
    {
        return $this->defaultItemsPerPage;
    }

    public function normalizeCityItemsPerPage(int $itemsPerPage): int
    {
        return in_array($itemsPerPage, $this->cityAllowedItemsPerPage, true)
            ? $itemsPerPage
            : $this->cityDefaultItemsPerPage;
    }

    /** @return int[] */
    public function getCityAllowedItemsPerPage(): array
    {
        return $this->cityAllowedItemsPerPage;
    }

    public function getCityDefaultItemsPerPage(): int
    {
        return $this->cityDefaultItemsPerPage;
    }

    public function normalizePvzItemsPerPage(int $itemsPerPage): int
    {
        return in_array($itemsPerPage, $this->pvzAllowedItemsPerPage, true)
            ? $itemsPerPage
            : $this->pvzDefaultItemsPerPage;
    }

    /** @return int[] */
    public function getPvzAllowedItemsPerPage(): array
    {
        return $this->pvzAllowedItemsPerPage;
    }

    public function getPvzDefaultItemsPerPage(): int
    {
        return $this->pvzDefaultItemsPerPage;
    }
}


