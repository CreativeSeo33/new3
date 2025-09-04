<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Repository\CategoryRepository;

final class FooterCategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * @return Category[]
     */
    public function getFooterCategories(): array
    {
        return $this->categoryRepository->findByFooterVisibility(true);
    }
}

final class NavbarCategoryService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {}

    /**
     * @return Category[]
     */
    public function getNavbarCategories(): array
    {
        return $this->categoryRepository->findByNavbarVisibility(true);
    }
}