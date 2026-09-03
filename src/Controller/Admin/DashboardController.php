<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Service\DashboardStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(DashboardStats $stats, OrderRepository $orders): Response
    {
        $top = $stats->topProducts(5);
        $qtys = array_column($top, 'qty');

        return $this->render('admin/dashboard.html.twig', [
            'overview' => $stats->overview(),
            'lowStock' => $stats->lowStock(),
            'series' => $stats->dailySeries(14),
            'top' => $top,
            'topMax' => [] === $qtys ? 1 : max($qtys),
            'recentOrders' => $orders->recent(6),
        ]);
    }
}
