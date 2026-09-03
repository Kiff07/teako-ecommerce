<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImage>
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    public function findMainImage(int $productId): ?ProductImage
    {
        return $this->createQueryBuilder('i')
            ->where('i.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('i.isMain', 'DESC')
            ->addOrderBy('i.displayOrder', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
