<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/commandes')]
class OrderAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'admin_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orders): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 15;
        $status = (string) $request->query->get('status', '');
        $q = trim((string) $request->query->get('q', ''));

        $qb = $orders->createQueryBuilder('o')
            ->leftJoin('o.items', 'oi')
            ->addSelect('o');
        if ('' !== $status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ('' !== $q) {
            $qb->andWhere('o.orderNumber LIKE :q OR o.customerName LIKE :q OR o.customerEmail LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        $total = (clone $qb)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('o.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $allStatuses = OrderStatus::cases();

        return $this->render('admin/order/index.html.twig', [
            'orders' => $items,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'total' => (int) $total,
            'status' => $status,
            'q' => $q,
            'statuses' => $allStatuses,
            'counts' => $orders->countByStatus(),
        ]);
    }

    #[Route('/{id}', name: 'admin_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        // force-load items/products for the view
        $order->getItems()->toArray();

        return $this->render('admin/order/show.html.twig', [
            'order' => $order,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    #[Route('/{id}/statut', name: 'admin_order_status', methods: ['POST'])]
    public function status(Request $request, Order $order): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $next = OrderStatus::tryFrom((string) ($payload['status'] ?? ''));
        if (null === $next) {
            return $this->json(['ok' => false, 'message' => 'Statut inconnu.'], Response::HTTP_BAD_REQUEST);
        }

        $order->setStatus($next);
        $this->em->flush();

        return $this->json(['ok' => true, 'status' => $next->value, 'label' => $next->label()]);
    }
}
