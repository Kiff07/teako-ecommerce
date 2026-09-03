<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function recent(int $limit = 10): array
    {
        return $this->createQueryBuilder('o')
            ->addSelect('oi')
            ->leftJoin('o.items', 'oi')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Revenue for a window. Null `from`/`to` means unbounded. */
    public function revenueBetween(?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        $qb = $this->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.totalAmount), 0)')
            ->where('o.status != :cancelled')
            ->setParameter('cancelled', OrderStatus::CANCELLED->value);

        if (null !== $from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }

        return (int) round((float) $qb->getQuery()->getSingleScalarResult());
    }

    public function countBetween(?\DateTimeInterface $from, ?\DateTimeInterface $to): int
    {
        $qb = $this->createQueryBuilder('o')->select('COUNT(o.id)');

        if (null !== $from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status', 'COUNT(o.id) AS cnt')
            ->groupBy('o.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $value = $row['status'];
            if ($value instanceof OrderStatus) {
                $value = $value->value;
            }
            $counts[(string) $value] = (int) $row['cnt'];
        }
        foreach (OrderStatus::cases() as $status) {
            $counts[$status->value] ??= 0;
        }

        return $counts;
    }

    /** @return array<int, array{label: string, revenue: int, orders: int}> */
    public function dailySeries(?\DateTimeInterface $from, ?\DateTimeInterface $to): array
    {
        $all = $this->createQueryBuilder('o')
            ->select('o.createdAt', 'o.status')
            ->addSelect('o.totalAmount')
            ->where('o.createdAt >= :from')
            ->andWhere('o.createdAt <= :to')
            ->andWhere('o.status != :cancelled')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('cancelled', OrderStatus::CANCELLED->value)
            ->getQuery()
            ->getResult();

        $series = [];
        $day = \DateTimeImmutable::createFromInterface($from);
        $end = \DateTimeImmutable::createFromInterface($to);
        $end = $end->setTime(23, 59, 59);

        while ($day <= $end) {
            $label = $day->format('d/m');
            $series[$day->format('Y-m-d')] = ['label' => $label, 'revenue' => 0, 'orders' => 0];
            $day = $day->modify('+1 day');
        }

        foreach ($all as $row) {
            $key = $row['createdAt']->format('Y-m-d');
            if (!isset($series[$key])) {
                continue;
            }
            $series[$key]['revenue'] = (int) round(((float) $row['totalAmount']) * 100);
            ++$series[$key]['orders'];
        }

        return array_values($series);
    }

    /** Units sold per product over a window, for the top-N chart. */
    public function topProducts(?\DateTimeInterface $from, ?\DateTimeInterface $to, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('o')
            ->select('p.name AS name', 'SUM(oi.quantity) AS qty', 'SUM(oi.quantity * oi.unitPrice) AS revenue')
            ->innerJoin('o.items', 'oi')
            ->innerJoin('oi.product', 'p')
            ->where('o.status != :cancelled')
            ->setParameter('cancelled', OrderStatus::CANCELLED->value)
            ->groupBy('p.id', 'p.name')
            ->orderBy('qty', 'DESC')
            ->setMaxResults($limit);

        if (null !== $from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        if (null !== $to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /** Largest order number prefix counter used to build sequential human-readable numbers. */
    public function lastOrderIndex(\DateTimeInterface $day): int
    {
        $start = $day->setTime(0, 0, 0);
        $end = $day->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('o');
        $qb->select('o.orderNumber')
            ->where('o.createdAt >= :start')
            ->andWhere('o.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1);

        $last = $qb->getQuery()->getOneOrNullResult();

        if (null === $last || null === $last['orderNumber']) {
            return 0;
        }

        if (preg_match('/-(\d+)$/', (string) $last['orderNumber'], $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
