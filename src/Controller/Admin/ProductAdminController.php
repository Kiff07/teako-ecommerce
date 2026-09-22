<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Form\ProductFormType;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\ImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/produits')]
class ProductAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageProcessor $images,
    ) {
    }

    #[Route('', name: 'admin_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products, CategoryRepository $categories): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 12;
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        $qb = $products->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c');
        if ('' !== $q) {
            $qb->andWhere('p.name LIKE :q OR p.slug LIKE :q')->setParameter('q', '%'.$q.'%');
        }
        if ('actifs' === $status) {
            $qb->andWhere('p.isActive = true');
        } elseif ('inactifs' === $status) {
            $qb->andWhere('p.isActive = false');
        } elseif ('rupture' === $status) {
            $qb->andWhere('p.stock = 0');
        }

        $total = (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/product/index.html.twig', [
            'products' => $items,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'total' => (int) $total,
            'q' => $q,
            'status' => $status,
            'categories' => $categories->findAll(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductFormType::class, $product, ['csrf_token_id' => 'product_form']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setStock($product->getStock() ?? 0);
            $this->em->persist($product);
            $this->em->flush(); // id is now available for the media folder

            $this->reconcileImages($product, $this->decodeItems($form->get('imagesJson')->getData()));
            $this->em->flush();

            $this->addFlash('success', 'Produit « '.$product->getName().' » créé.');

            return $this->redirectToRoute('admin_product_index');
        }

        return $this->render('admin/product/form.html.twig', [
            'product' => $product,
            'form' => $form,
            'imagesJson' => '[]',
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_product_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product): Response
    {
        $form = $this->createForm(ProductFormType::class, $product, ['csrf_token_id' => 'product_form']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->reconcileImages($product, $this->decodeItems($form->get('imagesJson')->getData()));
            $this->em->flush();

            $this->addFlash('success', 'Produit « '.$product->getName().' » mis à jour.');

            return $this->redirectToRoute('admin_product_index');
        }

        $current = [];
        foreach ($product->getImages() as $image) {
            $current[] = [
                'filename' => $image->getFilename(),
                'alt' => $image->getAltText(),
                'main' => $image->isMain(),
                'order' => $image->getDisplayOrder(),
            ];
        }

        return $this->render('admin/product/form.html.twig', [
            'product' => $product,
            'form' => $form,
            'imagesJson' => json_encode($current),
        ]);
    }

    #[Route('/{id}/basculer', name: 'admin_product_toggle', methods: ['POST'])]
    public function toggle(Request $request, Product $product): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }
        $product->setIsActive(!$product->isActive());
        $this->em->flush();

        return $this->json(['ok' => true, 'active' => $product->isActive()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product): Response
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_product_index');
        }

        $name = $product->getName();
        $id = $product->getId() ?? 0;
        $this->em->remove($product);
        $this->em->flush();
        $this->images->removeProductFiles($id);

        $this->addFlash('success', 'Produit « '.$name.' » supprimé.');

        return $this->redirectToRoute('admin_product_index');
    }

    #[Route('/upload', name: 'admin_image_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (null === $file || !$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['ok' => false, 'message' => 'Aucun fichier reçu.'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->images->isSupported($file)) {
            return $this->json([
                'ok' => false,
                'message' => 'Format non supporté ou fichier > 5 Mo (JPEG, PNG ou WebP uniquement).',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $fileSize = $file->getSize(); // read before any move() invalidates the temp file

        $productId = (int) $request->request->get('productId', 0);
        try {
            if ($productId > 0) {
                $url = $this->images->storeUpload($file, $productId);
            } else {
                $token = (string) $request->request->get('token', 'u'.bin2hex(random_bytes(8)));
                $url = $this->images->storeTempUpload($file, $token);
            }
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => 'Enregistrement impossible.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        [$w, $h] = array_values($this->images->dimensionsOf($url));
        // Pre-warm the thumb variant now: the browser preview needs it right
        // away, and /media/... is served as a plain static file, so nothing
        // else would generate it before the next full page render.
        $thumb = $this->images->variant($url, 'thumb')['url'];

        return $this->json(['ok' => true, 'url' => $url, 'thumb' => $thumb, 'width' => $w, 'height' => $h, 'size' => $fileSize]);
    }

    #[Route('/recadrer', name: 'admin_image_crop', methods: ['POST'])]
    public function crop(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        $url = (string) ($payload['url'] ?? '');
        if ('' === $url) {
            return $this->json(['ok' => false, 'message' => 'Image manquante.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->images->crop($url, (float) ($payload['x'] ?? 0), (float) ($payload['y'] ?? 0), (float) ($payload['w'] ?? 1), (float) ($payload['h'] ?? 1));
            $thumb = $this->images->variant($url, 'thumb')['url'];
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(['ok' => true, 'url' => $url, 'thumb' => $thumb]);
    }

    #[Route('/fichier', name: 'admin_image_delete', methods: ['POST'])]
    public function deleteFile(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_actions', (string) $request->headers->get('X-CSRF-TOKEN', ''))) {
            return $this->json(['ok' => false], Response::HTTP_FORBIDDEN);
        }

        $url = (string) $request->request->get('url', '');
        if (str_starts_with($url, '/uploads/tmp/')) {
            $this->images->deleteFile($url);
        }
        // Files already attached to a product are only physically removed when
        // the product form is saved (they may still be referenced in the DB).

        return $this->json(['ok' => true]);
    }

    /** @return list<array{filename?: string, alt?: string, main?: bool, order?: int}> */
    private function decodeItems(?string $json): array
    {
        if (null === $json || '' === trim($json)) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($item) => is_array($item) && isset($item['filename']) && '' !== (string) $item['filename']));
    }

    /**
     * @param list<array{filename?: string, alt?: string, main?: bool, order?: int}> $items
     */
    private function reconcileImages(Product $product, array $items): void
    {
        $map = [];
        foreach ($product->getImages() as $image) {
            $map[(string) $image->getFilename()] = $image;
        }

        $kept = [];
        $order = 0;
        foreach ($items as $i => $item) {
            $filename = (string) ($item['filename'] ?? '');
            if ('' === $filename) {
                continue;
            }
            ++$order;

            $image = $map[$filename] ?? null;
            if (null !== $image) {
                unset($map[$filename]);
            } else {
                if (str_starts_with($filename, '/uploads/tmp/')) {
                    $filename = $this->images->adoptTemp($filename, (int) $product->getId());
                }
                $image = new ProductImage();
                $image->setFilename($filename);
                $product->addImage($image);
                $this->em->persist($image);
            }

            $image->setAltText(isset($item['alt']) && '' !== trim((string) $item['alt']) ? trim((string) $item['alt']) : $product->getName());
            $image->setDisplayOrder($order);
            $image->setIsMain(false);
            if (!empty($item['main'])) {
                $image->setIsMain(true);
            }
            $kept[] = $image;
        }

        foreach ($map as $orphan) {
            $product->removeImage($orphan);
            $this->em->remove($orphan);
            if (null !== $orphan->getFilename()) {
                $this->images->deleteFile($orphan->getFilename());
            }
        }

        // Guarantee a main image exists when there are images at all
        if ([] !== $kept && !array_any($kept, static fn ($img) => $img->isMain())) {
            $kept[0]->setIsMain(true);
        }
    }
}