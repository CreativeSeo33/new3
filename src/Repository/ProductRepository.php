<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\ProductAttributeAssignment;

final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findOneActiveWithAttributesBySlug(string $slug): ?Product
    {
        // Сначала загружаем товар без атрибутов
        $product = $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.status = true')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
            
        if ($product === null) {
            return null;
        }
        
        // Затем загружаем все атрибуты для этого товара
        $attributeAssignments = $this->getEntityManager()->createQueryBuilder()
            ->select('paa', 'ag', 'attr')
            ->from(ProductAttributeAssignment::class, 'paa')
            ->leftJoin('paa.attributeGroup', 'ag')
            ->leftJoin('paa.attribute', 'attr')
            ->where('paa.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();
            
        // Очищаем коллекцию и добавляем загруженные атрибуты
        $product->getAttributeAssignments()->clear();
        foreach ($attributeAssignments as $assignment) {
            $product->addAttributeAssignment($assignment);
        }
        
        return $product;
    }
    public function save(Product $entity, bool $flush = false): void
    {
        $this->_em->persist($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->_em->remove($entity);
        if ($flush) {
            $this->_em->flush();
        }
    }

    /**
     * @return Product[]
     */
    public function findActiveByCategory(Category $category, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->distinct()
            ->innerJoin('p.category', 'pc')
            ->leftJoin('p.image', 'img')->addSelect('img')
            ->andWhere('pc.category = :category')
            ->andWhere('pc.visibility = true')
            ->andWhere('p.status = true')
            ->orderBy('p.sortOrder', 'ASC')
            ->addOrderBy('img.sortOrder', 'ASC')
            ->setParameter('category', $category)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}


