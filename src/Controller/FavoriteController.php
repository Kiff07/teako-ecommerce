<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FavoriteController extends AbstractController
{
    public const COOKIE = 'tk_favs';
    private const MAX = 30;

    #[Route('/favoris/basculer/{id}', name: 'fav_toggle', methods: ['POST'])]
    public function toggle(Request $request, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('cart', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Session expirée.'], Response::HTTP_FORBIDDEN);
        }

        $ids = $this->read($request);
        if (in_array($id, $ids, true)) {
            $ids = array_values(array_filter($ids, fn ($i) => $i !== $id));
            $active = false;
        } else {
            $ids[] = $id;
            $ids = array_slice(array_values(array_unique($ids)), -self::MAX);
            $active = true;
        }

        $cookie = Cookie::create(self::COOKIE, implode(',', $ids), time() + 60 * 60 * 24 * 90, '/');

        $response = $this->json(['ok' => true, 'active' => $active, 'count' => count($ids)]);
        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/favoris', name: 'fav_list')]
    public function list(Request $request, ProductRepository $products): Response
    {
        $ids = $this->read($request);

        return $this->render('store/favorites.html.twig', [
            'ids' => $ids,
            'products' => $products->findByIds($ids),
        ]);
    }

    /** @return list<int> */
    private function read(Request $request): array
    {
        $raw = $request->cookies->get(self::COOKIE, '');

        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $raw)))));
    }
}
