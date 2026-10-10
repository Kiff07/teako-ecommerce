<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de sécurité HTTP de base sur toutes les réponses :
 *  - X-Content-Type-Options: nosniff  (anti MIME-sniffing)
 *  - X-Frame-Options: DENY sur /admin (anti clickjacking), SAMEORIGIN ailleurs
 *  - Referrer-Policy + Permissions-Policy
 *
 * Une CSP stricte n'est pas activée ici car la boutique charge Tailwind et
 * Stimulus depuis des CDN + styles inline ; voir AUDIT.md pour la marche à
 * suivre si vous souhaitez la mettre en place (nonces ou build local).
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set(
            'X-Frame-Options',
            str_starts_with($event->getRequest()->getPathInfo(), '/admin') ? 'DENY' : 'SAMEORIGIN'
        );
    }
}
