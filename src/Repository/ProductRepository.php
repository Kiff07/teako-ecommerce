<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findActiveBySlug(string $slug): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.slug = :slug')
            ->andWhere('p.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveOrdered(?string $categorySlug = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');

        if (null !== $categorySlug) {
            $qb->andWhere('c.slug = :slug')->setParameter('slug', $categorySlug);
        }

        return $qb
            ->addOrderBy('p.isActive', 'DESC')
            ->addOrderBy('p.salesCount', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNewest(int $limit = 6, ?string $categorySlug = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');

        if (null !== $categorySlug) {
            $qb->andWhere('c.slug = :slug')->setParameter('slug', $categorySlug);
        }

        return $qb
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBestSellers(int $limit = 6, ?string $categorySlug = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');

        if (null !== $categorySlug) {
            $qb->andWhere('c.slug = :slug')->setParameter('slug', $categorySlug);
        }

        return $qb
            ->orderBy('p.salesCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecommended(Product $product, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.id != :id')
            ->setParameter('id', $product->getId());

        if (null !== $product->getCategory()) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $product->getCategory());
        }

        return $qb
            ->addOrderBy('p.salesCount', 'DESC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->andWhere('p.isActive = true')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findMenu(?string $categorySlug = null): array
    {
        return $this->findActiveOrdered($categorySlug);
    }
}
