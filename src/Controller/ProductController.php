<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/produit/{slug}', name: 'product_show')]
    public function show(
        Product $product,
        ProductRepository $products,
    ): Response {
        if (!$product->isActive()) {
            throw $this->createNotFoundException('Produit indisponible.');
        }

        // Ensure the image collection is ordered (main first, then display order)
        $images = $product->getImages()->toArray();
        usort($images, static function ($a, $b) {
            if ($a->isMain() !== $b->isMain()) {
                return $a->isMain() ? -1 : 1;
            }

            return $a->getDisplayOrder() <=> $b->getDisplayOrder();
        });

        return $this->render('store/product_show.html.twig', [
            'product' => $product,
            'images' => $images,
            'recommended' => $products->findRecommended($product, 4),
        ]);
    }

    #[Route('/decouvrir', name: 'discover')]
    public function discover(ProductRepository $products, CategoryRepository $categories): Response
    {
        return $this->render('store/discover.html.twig', [
            'products' => $products->findBestSellers(9),
            'categories' => array_values(array_filter($categories->findActiveMenuCategories(), fn ($c) => count($c->getProducts()) > 0)),
        ]);
    }
}
