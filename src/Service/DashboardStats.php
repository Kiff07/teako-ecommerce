<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;

class DashboardStats
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly ProductRepository $products,
    ) {
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $now = new \DateTimeImmutable('now');
        $today = $now->setTime(0, 0, 0);
        $monthAgo = $today->modify('-29 days');
        $yesterdayStart = $today->modify('-1 day');

        $todayRevenue = $this->orders->revenueBetween($today, null);
        $monthRevenue = $this->orders->revenueBetween($monthAgo, null);
        $yesterdayRevenue = $this->orders->revenueBetween($yesterdayStart, $today);
        $totalRevenue = $this->orders->revenueBetween(null, null);

        $todayCount = $this->orders->countBetween($today, null);
        $monthCount = $this->orders->countBetween($monthAgo, null);
        $totalCount = $this->orders->countBetween(null, null);

        return [
            'todayRevenueCents' => $todayRevenue,
            'monthRevenueCents' => $monthRevenue,
            'totalRevenueCents' => $totalRevenue,
            'yesterdayRevenueCents' => $yesterdayRevenue,
            'todayCount' => $todayCount,
            'monthCount' => $monthCount,
            'totalCount' => $totalCount,
            'averageCents' => $totalCount > 0 ? (int) round($totalRevenue / $totalCount) : 0,
            'statusCounts' => $this->orders->countByStatus(),
        ];
    }

    /** @return array<string, mixed> */
    public function lowStock(int $threshold = 8): array
    {
        return $this->products->createQueryBuilder('p')
            ->where('p.stock < :threshold')
            ->andWhere('p.isActive = true')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();
    }

    /** @return array{labels: list<string>, revenue: list<int>, orders: list<int>} */
    public function dailySeries(int $days = 14): array
    {
        $today = (new \DateTimeImmutable('now'))->setTime(0, 0, 0);
        $start = $today->modify('-'.($days - 1).' days');
        $end = $today->setTime(23, 59, 59);

        $rows = $this->orders->dailySeries($start, $end);
        $labels = array_map(fn ($r) => $r['label'], $rows);
        $revenue = array_map(fn ($r) => (int) round($r['revenue'] / 100), $rows);
        $orders = array_map(fn ($r) => $r['orders'], $rows);

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'orders' => $orders,
            'maxRevenue' => [] === $revenue ? 0 : max($revenue),
        ];
    }

    /** @return array{name: string, qty: int, revenueCents: int}[] */
    public function topProducts(int $limit = 5): array
    {
        $monthAgo = (new \DateTimeImmutable('now'))->modify('-29 days');
        $rows = $this->orders->topProducts($monthAgo, null, $limit);

        return array_map(static fn ($r) => [
            'name' => (string) $r['name'],
            'qty' => (int) $r['qty'],
            'revenueCents' => (int) round(((float) $r['revenue']) * 100),
        ], $rows);
    }
}
