<?php

declare(strict_types=1);

namespace App\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

class CartSession
{
    public const COOKIE = 'tk_cart';
    private const LIFETIME = 60 * 60 * 24 * 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CartRepository $repository,
    ) {
    }

    public static function cookieFor(string $token): Cookie
    {
        return Cookie::create(self::COOKIE, $token, time() + self::LIFETIME, '/', null, false, true, false, 'lax');
    }

    public static function clearCookie(): Cookie
    {
        return Cookie::create(self::COOKIE, '', time() - 3600, '/');
    }

    public function tokenFrom(Request $request): ?string
    {
        return $request->cookies->get(self::COOKIE);
    }

    public function findByRequest(Request $request): ?Cart
    {
        $token = $this->tokenFrom($request);

        return null === $token ? null : $this->repository->findOneByToken($token);
    }

    /**
     * Return the cart attached to the request or a brand new empty one.
     * It is only persisted to the database the first time an item is added,
     * keeping abandoned rows out of the tables.
     */
    public function findOrCreate(Request $request): Cart
    {
        $cart = $this->findByRequest($request);
        if (null === $cart) {
            $cart = new Cart();
            $cart->setToken(bin2hex(random_bytes(24)));
            $cart->setCookieId(bin2hex(random_bytes(16)));
            $this->em->persist($cart);
            $this->em->flush();
        }

        return $cart;
    }

    /** Empty a cart without deleting it (keeps the token/cookie stable). */
    public function clear(Cart $cart): void
    {
        foreach ($cart->getItems() as $item) {
            $this->em->remove($item);
        }
        $cart->getItems()->clear();
        $cart->touch();
        $this->em->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            $lines[] = [
                'lineId' => $item->getId(),
                'productId' => $product?->getId(),
                'name' => $product?->getName() ?? '',
                'slug' => $product?->getSlug(),
                'unitPriceCents' => $product?->getPriceCents() ?? 0,
                'quantity' => $item->getQuantity(),
                'lineTotalCents' => $item->getLineTotalCents(),
                'stock' => $product?->getStock() ?? 0,
                'image' => $this->imageFor($item),
            ];
        }

        return [
            'token' => $cart->getToken(),
            'count' => $cart->getItemCount(),
            'linesCount' => count($lines),
            'totalCents' => $cart->getTotalCents(),
            'lines' => $lines,
        ];
    }

    private function imageFor(CartItem $item): string
    {
        $product = $item->getProduct();
        $filename = $product?->getCover()?->getFilename();
        if (null === $filename || '' === $filename) {
            return '/img/placeholder.svg';
        }
        if (str_starts_with($filename, 'http')) {
            return $filename;
        }

        // Serve the square variant when it has already been generated (it is
        // created lazily server-side by the Twig runtime on first view).
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $variant = preg_replace('/\.'.preg_quote($ext, '/').'$/', '-thumb.jpg', $filename);
        $variantPath = \dirname(__DIR__, 2).'/public'.$variant;
        if (is_file($variantPath)) {
            return $variant;
        }

        return $filename;
    }
}
