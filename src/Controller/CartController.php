<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cart\CartSession;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/panier')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartSession $cartSession,
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $products,
    ) {
    }

    #[Route('', name: 'cart_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        $cart = $this->cartSession->findByRequest($request);

        return $this->render('store/cart.html.twig', [
            'cart' => $cart,
            'cartJson' => $cart ? $this->cartSession->toArray($cart) : ['count' => 0, 'linesCount' => 0, 'totalCents' => 0, 'lines' => [], 'token' => null],
        ]);
    }

    #[Route('/etat', name: 'cart_state', methods: ['GET'])]
    public function state(Request $request): JsonResponse
    {
        $cart = $this->cartSession->findByRequest($request);

        return $this->json($cart ? $this->cartSession->toArray($cart) : ['count' => 0, 'linesCount' => 0, 'totalCents' => 0, 'lines' => [], 'token' => null]);
    }

    #[Route('/ajouter/{id}', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request, Product $product): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        if (!$product->isActive() || $product->isOutOfStock()) {
            return $this->json(['ok' => false, 'message' => 'Ce produit est en rupture de stock.'], Response::HTTP_CONFLICT);
        }

        $qty = max(1, (int) $request->request->get('qty', 1));
        $qty = min($qty, 8);

        $cart = $this->cartSession->findOrCreate($request);

        $item = $this->existingLine($cart, $product);
        $stockLeft = $product->getStock() - ($item ? $item->getQuantity() : 0);
        if ($qty > $stockLeft) {
            $qty = max(1, $stockLeft);
            $message = sprintf('Stock limité : %d ajouté(s) au panier.', $qty);
        } else {
            $message = 'Ajouté au panier !';
        }

        if (null === $item) {
            $item = new CartItem();
            $item->setProduct($product);
            $item->setCart($cart);
            $cart->addItem($item);
            $this->em->persist($item);
            $qtyOnItem = 0; // fresh line starts empty; the default 1 must not leak in
        } else {
            $qtyOnItem = $item->getQuantity();
        }
        $item->setQuantity($qtyOnItem + $qty);
        $cart->touch();
        $this->em->flush();

        $response = $this->json([
            'ok' => true,
            'message' => $message,
            'data' => $this->cartSession->toArray($cart),
        ]);
        $response->headers->setCookie(CartSession::cookieFor($cart->getToken()));

        return $response;
    }

    #[Route('/maj/{id}', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request, CartItem $item): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $cart = $item->getCart();
        if (null === $cart || $cart->getToken() !== $this->cartSession->tokenFrom($request)) {
            return $this->json(['ok' => false, 'message' => 'Panier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $qty = (int) $request->request->get('qty', 1);
        $product = $item->getProduct();

        if ($qty <= 0) {
            $this->em->remove($item);
        } else {
            $max = $product?->getStock() ?? $qty;
            $item->setQuantity(min($qty, max(1, $max)));
            $cart->touch();
        }

        $this->em->flush();

        return $this->json([
            'ok' => true,
            'data' => $this->cartSession->toArray($cart),
        ]);
    }

    #[Route('/supprimer/{id}', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request, CartItem $item): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $cart = $item->getCart();
        if (null === $cart || $cart->getToken() !== $this->cartSession->tokenFrom($request)) {
            return $this->json(['ok' => false, 'message' => 'Panier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->remove($item);
        $cart->touch();
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'data' => $this->cartSession->toArray($cart),
        ]);
    }

    #[Route('/vider', name: 'cart_clear', methods: ['POST'])]
    public function clearAll(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $cart = $this->cartSession->findByRequest($request);
        if (null !== $cart) {
            $this->cartSession->clear($cart);
        }

        return $this->json(['ok' => true, 'data' => ['count' => 0, 'linesCount' => 0, 'totalCents' => 0, 'lines' => [], 'token' => $cart?->getToken()]]);
    }

    private function existingLine(Cart $cart, Product $product): ?CartItem
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getProduct()?->getId() === $product->getId()) {
                return $item;
            }
        }

        return null;
    }
}
