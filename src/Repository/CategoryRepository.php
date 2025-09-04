<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function findOneVisibleBySlug(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.visibility = :visible')
            ->setParameter('slug', $slug)
            ->setParameter('visible', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Category[] Ancestors ordered from root to direct parent
     */
    public function getAncestorsFor(Category $category, int $maxLevels = 3): array
    {
        $ancestors = [];
        $current = $category;
        $level = 0;

        while ($current->getParentCategoryId() !== null && $level < $maxLevels) {
            $parent = $this->find($current->getParentCategoryId());
            if ($parent === null) {
                break;
            }
            array_unshift($ancestors, $parent);
            $current = $parent;
            $level++;
        }

        return $ancestors;
    }

    /**
     * @return Category[] Categories visible in footer, ordered by sortOrder
     */
    public function findByFooterVisibility(bool $footerVisibility = true): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.footerVisibility = :footerVisible')
            ->andWhere('c.visibility = :visible')
            ->andWhere('c.name IS NOT NULL')
            ->setParameter('footerVisible', $footerVisibility)
            ->setParameter('visible', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Category[] Categories visible in navbar, ordered by sortOrder
     */
    public function findByNavbarVisibility(bool $navbarVisibility = true): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.navbarVisibility = :navbarVisible')
            ->andWhere('c.visibility = :visible')
            ->andWhere('c.name IS NOT NULL')
            ->setParameter('navbarVisible', $navbarVisibility)
            ->setParameter('visible', true)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}


