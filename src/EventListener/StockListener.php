<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Automatic stock management: each time an order is created, the ordered
 * quantities are removed from stock and added to the product sales counters.
 * Only a transition towards "cancelled" restores the stock (and a transition
 * away from cancelled removes it again).
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class StockListener
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Order) {
            $this->apply($entity);
            $this->em->flush(); // persist the stock/sales changes right away
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Order) {
            return;
        }

        $changes = $this->em->getUnitOfWork()->getEntityChangeSet($entity);
        if (!isset($changes['status'])) {
            return;
        }

        [$old, $new] = $changes['status'];
        $wasCancelled = $this->isCancelled($old);
        $isCancelled = $this->isCancelled($new);

        if ($isCancelled && !$wasCancelled) {
            $this->restore($entity);
            $this->em->flush();
        } elseif (!$isCancelled && $wasCancelled) {
            $this->apply($entity);
            $this->em->flush();
        }
    }

    private function isCancelled(mixed $status): bool
    {
        if ($status instanceof OrderStatus) {
            return OrderStatus::CANCELLED === $status;
        }

        return OrderStatus::CANCELLED->value === (string) $status;
    }

    private function apply(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue;
            }
            $product->setStock($product->getStock() - $item->getQuantity());
            $product->incrementSales($item->getQuantity());
        }
    }

    private function restore(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue;
            }
            $product->setStock($product->getStock() + $item->getQuantity());
        }
    }
}
