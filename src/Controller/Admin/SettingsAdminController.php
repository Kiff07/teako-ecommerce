<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ShopSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/parametres')]
class SettingsAdminController extends AbstractController
{
    #[Route('', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, ShopSettingsService $settingsService): Response
    {
        $settings = $settingsService->getSettings();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_settings', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('admin_settings');
            }

            $payload = [
                'phone' => trim((string) $request->request->get('phone')),
                'whatsapp' => trim((string) $request->request->get('whatsapp')),
                'email' => trim((string) $request->request->get('email')),
                'address' => trim((string) $request->request->get('address')),
                'hours' => trim((string) $request->request->get('hours')),
                'banner_text' => trim((string) $request->request->get('banner_text')),
                'delivery_zone' => trim((string) $request->request->get('delivery_zone')),
                'prep_time' => trim((string) $request->request->get('prep_time')),
                'instagram' => trim((string) $request->request->get('instagram')),
                'facebook' => trim((string) $request->request->get('facebook')),
                'tiktok' => trim((string) $request->request->get('tiktok')),
                'is_open' => $request->request->has('is_open'),
                'announcement' => trim((string) $request->request->get('announcement')),
            ];

            $settingsService->saveSettings($payload);
            $this->addFlash('success', 'Paramètres de la boutique enregistrés avec succès.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', [
            'settings' => $settings,
        ]);
    }
}