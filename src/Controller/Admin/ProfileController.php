<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Admin;
use App\Form\AdminProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/profil')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'admin_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        /** @var Admin $admin */
        $admin = $this->getUser();
        if (!$admin instanceof Admin) {
            return $this->redirectToRoute('admin_login');
        }

        $form = $this->createForm(AdminProfileType::class, $admin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $filename = 'admin-' . uniqid() . '.' . $avatarFile->guessExtension();
                $avatarFile->move($uploadDir, $filename);

                if ($admin->getAvatar() && file_exists($this->getParameter('kernel.project_dir') . '/public' . $admin->getAvatar())) {
                    @unlink($this->getParameter('kernel.project_dir') . '/public' . $admin->getAvatar());
                }

                $admin->setAvatar('/uploads/avatars/' . $filename);
            }

            $newPassword = $form->get('newPassword')->getData();
            if (!empty($newPassword)) {
                $admin->setPassword($hasher->hashPassword($admin, $newPassword));
            }

            $em->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour.');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile.html.twig', [
            'form' => $form->createView(),
            'admin' => $admin,
        ]);
    }
}