<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function findOneByToken(string $token): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->addSelect('ci', 'p')
            ->leftJoin('c.items', 'ci')
            ->leftJoin('ci.product', 'p')
            ->where('c.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByCookieId(string $cookieId): ?Cart
    {
        return $this->findOneBy(['cookieId' => $cookieId]);
    }
}
