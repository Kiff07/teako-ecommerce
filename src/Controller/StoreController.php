<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(ProductRepository $products, CategoryRepository $categories): Response
    {
        $all = $products->findActiveOrdered();
        $best = array_slice($all, 0, 6);
        $fresh = $products->findNewest(3);
        $categories = array_filter($categories->findActiveMenuCategories(), fn ($c) => count($c->getProducts()) > 0);

        $hero = null;
        foreach ($all as $p) {
            if ('iced-caramel-macchiato' === $p->getSlug()) {
                $hero = $p;
                break;
            }
        }
        $hero = $hero ?? ($best[0] ?? null);
        // hero product always in the spotlight grid
        if (null !== $hero && !in_array($hero, $best, true)) {
            array_unshift($best, $hero);
            $best = array_slice($best, 0, 6);
        }

        return $this->render('store/home.html.twig', [
            'best' => $best,
            'all' => $all,
            'fresh' => $fresh,
            'categories' => array_values($categories),
            'hero' => $hero,
        ]);
    }

    #[Route('/carte', name: 'menu')]
    public function menu(ProductRepository $products, CategoryRepository $categories): Response
    {
        $categories = array_values(array_filter($categories->findActiveMenuCategories(), fn ($c) => count($c->getProducts()) > 0));

        $byCategory = [];
        $uncategorized = [];
        foreach ($categories as $category) {
            $byCategory[$category->getId()] = ['category' => $category, 'items' => []];
        }
        foreach ($products->findMenu() as $product) {
            $category = $product->getCategory();
            if (null !== $category && isset($byCategory[$category->getId()])) {
                $byCategory[$category->getId()]['items'][] = $product;
            } else {
                $uncategorized[] = $product;
            }
        }
        $byCategory = array_values($byCategory);

        return $this->render('store/menu.html.twig', [
            'groups' => $byCategory,
            'uncategorized' => $uncategorized,
        ]);
    }

    #[Route('/a-propos', name: 'about')]
    public function about(): Response
    {
        return $this->render('store/about.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('store/contact.html.twig');
    }

    #[Route('/nos-services/{slug}', name: 'category_show')]
    public function category(Category $category, ProductRepository $products): Response
    {
        return $this->render('store/category.html.twig', [
            'category' => $category,
            'products' => $products->findActiveOrdered($category->getSlug()),
        ]);
    }
}
