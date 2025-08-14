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
}


