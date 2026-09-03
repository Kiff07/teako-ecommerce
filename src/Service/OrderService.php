<?php

declare(strict_types=1);

namespace App\Service;

use App\Cart\CartSession;
use App\Entity\Cart;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Form\Model\CheckoutData;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CustomerRepository $customers,
        private readonly CartSession $cartSession,
    ) {
    }

    /**
     * Turn the cart into an order. Throws OutOfStockException when a line
     * cannot be satisfied (stock changed since the cart was filled).
     */
    public function place(Cart $cart, CheckoutData $data): Order
    {
        $lines = [];
        $total = 0;

        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            if (null === $product) {
                continue;
            }

            $qty = $item->getQuantity();
            if ($qty > $product->getStock()) {
                throw new OutOfStockException(sprintf(
                    'Il ne reste que %d exemplaire(s) de « %s » en stock.',
                    max(0, $product->getStock()),
                    $product->getName()
                ));
            }

            $line = new OrderItem();
            $line->setProduct($product);
            $line->setQuantity($qty);
            $lines[] = $line;
            $total += $line->getLineTotalCents();
        }

        if ([] === $lines) {
            throw new OutOfStockException('Votre panier est vide.');
        }

        $order = new Order();
        $order->setCustomerName($data->name);
        $order->setCustomerEmail($data->email);
        $order->setCustomerPhone($data->phone);
        $order->setDeliveryAddress('delivery' === $data->deliveryMode ? $data->deliveryAddress : null);
        foreach ($lines as $line) {
            $order->addItem($line);
        }
        $order->setTotalAmount($total / 100);

        // Find or create the returning customer
        $customer = $this->customers->findOneByEmail($data->email);
        if (null === $customer) {
            $customer = new Customer();
            $customer->setEmail($data->email);
        }
        $customer->setFullName($data->name);
        if (null !== $data->phone) {
            $customer->setPhone($data->phone);
        }
        if (null !== $data->deliveryAddress && 'delivery' === $data->deliveryMode) {
            $customer->setAddress($data->deliveryAddress);
        }
        $order->setCustomer($customer);

        $this->em->persist($customer);
        $this->em->persist($order);
        $this->em->flush();

        // Empty the cart (kept in the database, ready to be reused)
        $this->cartSession->clear($cart);

        return $order;
    }
}
