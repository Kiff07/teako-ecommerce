<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    /**
     * This method is never executed: the security firewall intercepts the call.
     * It exists so that path('admin_logout') can be generated in templates.
     */
    #[Route('/admin/logout', name: 'admin_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new LogicException('Cette route est interceptée par le pare-feu de sécurité (logout).');
    }
}
