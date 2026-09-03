<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\CategoryFormType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/categories')]
class CategoryAdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', name: 'admin_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categories): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categories->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', 'Catégorie « '.$category->getName().' » créée.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/form.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category): Response
    {
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('admin_category_index');
        }

        return $this->render('admin/category/form.html.twig', [
            'form' => $form,
            'category' => $category,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        if (count($category->getProducts()) > 0) {
            return $this->json(['ok' => false, 'message' => 'Cette catégorie contient encore des produits.']);
        }

        $this->em->remove($category);
        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
