<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cart\CartSession;
use App\Entity\Order;
use App\Form\CheckoutType;
use App\Form\Model\CheckoutData;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use App\Service\OutOfStockException;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    #[Route('/commander', name: 'order_checkout')]
    public function checkout(
        Request $request,
        CartSession $cartSession,
        OrderService $orderService,
    ): Response {
        $cart = $cartSession->findByRequest($request);
        if (null === $cart || $cart->getItems()->count() === 0) {
            $this->addFlash('info', 'Votre panier est vide : ajoutez une gourmandise avant de commander.');

            return $this->redirectToRoute('menu');
        }

        $data = new CheckoutData();
        $form = $this->createForm(CheckoutType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('delivery' === $data->deliveryMode && (null === $data->deliveryAddress || '' === trim($data->deliveryAddress))) {
                $form->get('deliveryAddress')->addError(new FormError('Indiquez votre adresse de livraison.'));
            } else {
                try {
                    $order = $orderService->place($cart, $data);
                } catch (OutOfStockException $e) {
                    $this->addFlash('error', $e->getMessage());

                    return $this->redirectToRoute('cart_show');
                }

                $request->getSession()->set('last_order', $order->getOrderNumber());

                return $this->redirectToRoute('order_thanks');
            }
        }

        return $this->render('store/checkout.html.twig', [
            'cart' => $cart,
            'cartJson' => $cartSession->toArray($cart),
            'form' => $form,
        ]);
    }

    #[Route('/commande/merci', name: 'order_thanks')]
    public function thanks(Request $request, OrderRepository $orders): Response
    {
        $number = $request->getSession()->get('last_order');

        /** @var Order|null $order */
        $order = null;
        if (null !== $number) {
            $order = $orders->findOneBy(['orderNumber' => $number]);
        }

        if (null === $order) {
            return $this->render('store/thanks.html.twig', ['order' => null]);
        }

        return $this->render('store/thanks.html.twig', ['order' => $order]);
    }
}
