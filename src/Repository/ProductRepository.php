<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\ProductAttributeAssignment;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\QueryBuilder;

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

    /**
     * @param array<string,string[]> $filters code => [values]
     * @return Product[]
     */
    public function findActiveByCategoryWithFacets(Category $category, array $filters, int $limit = 20, int $offset = 0, ?int $priceMin = null, ?int $priceMax = null): array
    {
        $qb = $this->createQueryBuilder('p')
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
            ->setMaxResults($limit);

        if ($priceMin !== null) {
            $qb->andWhere('p.effectivePrice >= :priceMin')
               ->setParameter('priceMin', $priceMin);
        }
        if ($priceMax !== null) {
            $qb->andWhere('p.effectivePrice <= :priceMax')
               ->setParameter('priceMax', $priceMax);
        }

        $i = 0;
        $numericCodes = ['height', 'bulbs_count', 'lighting_area'];
        foreach ($filters as $code => $values) {
            if (empty($values)) { continue; }
            $i++;
            $lower = strtolower((string)$code);
            if (in_array($lower, $numericCodes, true)) {
                $valsParam = 'f_vals_' . $i;
                $field = $lower === 'bulbs_count' ? 'bulbsCount' : ($lower === 'lighting_area' ? 'lightingArea' : 'height');
                $existsNum = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pnum' . $i
                    . ' WHERE pnum' . $i . '.product = p AND pnum' . $i . '.' . $field . ' IN (:' . $valsParam . '))';
                $qb->andWhere($existsNum)
                   ->setParameter($valsParam, array_values(array_map('intval', $values)));
                continue;
            }

            $codeParam = 'f_code_' . $i;
            $valsParam = 'f_vals_' . $i;
            $existsAttr = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductAttributeAssignment paa' . $i . ' JOIN paa' . $i . '.attribute a' . $i . ' WHERE paa' . $i . '.product = p AND a' . $i . '.code = :' . $codeParam . ' AND paa' . $i . '.stringValue IN (:' . $valsParam . '))';
            $existsOpt = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pova' . $i . ' JOIN pova' . $i . '.option o' . $i . ' JOIN pova' . $i . '.value ov' . $i . ' WHERE pova' . $i . '.product = p AND o' . $i . '.code = :' . $codeParam . ' AND ov' . $i . '.value IN (:' . $valsParam . '))';
            $qb->andWhere('(' . $existsAttr . ' OR ' . $existsOpt . ')')
                ->setParameter($codeParam, $code)
                ->setParameter($valsParam, $values);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Пагинация без фасетов: возвращает массив с элементами и общим количеством.
     *
     * @return array{items: Product[], total: int}
     */
    public function paginateActiveByCategory(Category $category, int $page, int $limit): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('p')
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
            ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $items = iterator_to_array($paginator->getIterator());

        return [ 'items' => $items, 'total' => $total ];
    }

    /**
     * Пагинация с фасетами: возвращает массив с элементами и общим количеством.
     *
     * @param array<string,string[]> $filters
     * @return array{items: Product[], total: int}
     */
    public function paginateActiveByCategoryWithFacets(Category $category, array $filters, int $page, int $limit, ?int $priceMin = null, ?int $priceMax = null): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('p')
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
            ->setMaxResults($limit);

        if ($priceMin !== null) {
            $qb->andWhere('p.effectivePrice >= :priceMin')
               ->setParameter('priceMin', $priceMin);
        }
        if ($priceMax !== null) {
            $qb->andWhere('p.effectivePrice <= :priceMax')
               ->setParameter('priceMax', $priceMax);
        }

        $i = 0;
        $numericCodes = ['height', 'bulbs_count', 'lighting_area'];
        foreach ($filters as $code => $values) {
            if (empty($values)) { continue; }
            $i++;
            $lower = strtolower((string)$code);
            if (in_array($lower, $numericCodes, true)) {
                $valsParam = 'f_vals_' . $i;
                $field = $lower === 'bulbs_count' ? 'bulbsCount' : ($lower === 'lighting_area' ? 'lightingArea' : 'height');
                $existsNum = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pnum' . $i
                    . ' WHERE pnum' . $i . '.product = p AND pnum' . $i . '.' . $field . ' IN (:' . $valsParam . '))';
                $qb->andWhere($existsNum)
                   ->setParameter($valsParam, array_values(array_map('intval', $values)));
                continue;
            }

            $codeParam = 'f_code_' . $i;
            $valsParam = 'f_vals_' . $i;
            $existsAttr = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductAttributeAssignment paa' . $i . ' JOIN paa' . $i . '.attribute a' . $i . ' WHERE paa' . $i . '.product = p AND a' . $i . '.code = :' . $codeParam . ' AND paa' . $i . '.stringValue IN (:' . $valsParam . '))';
            $existsOpt = 'EXISTS (SELECT 1 FROM App\\Entity\\ProductOptionValueAssignment pova' . $i . ' JOIN pova' . $i . '.option o' . $i . ' JOIN pova' . $i . '.value ov' . $i . ' WHERE pova' . $i . '.product = p AND o' . $i . '.code = :' . $codeParam . ' AND ov' . $i . '.value IN (:' . $valsParam . '))';
            $qb->andWhere('(' . $existsAttr . ' OR ' . $existsOpt . ')')
                ->setParameter($codeParam, $code)
                ->setParameter($valsParam, $values);
        }

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $items = iterator_to_array($paginator->getIterator());

        return [ 'items' => $items, 'total' => $total ];
    }
}


