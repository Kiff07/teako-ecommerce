<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Twig\Environment;

/**
 * Protection anti force brute du login admin, sans dépendance supplémentaire :
 *  - chaque échec incrémente un compteur par IP (cache applicatif, TTL 15 min) ;
 *  - au-delà de 5 échecs, les tentatives suivantes sont rejetées avant même
 *    d'atteindre le firewall (réponse 429 avec la page de login) ;
 *  - un login réussi remet le compteur à zéro.
 */
final class LoginThrottlerSubscriber implements EventSubscriberInterface
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // priorité 16 : avant le firewall (priorité 8) pour bloquer tôt
            KernelEvents::REQUEST => ['onKernelRequest', 16],
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod() || '/admin/login' !== $request->getPathInfo()) {
            return;
        }

        $item = $this->cachePool->getItem($this->key($request->getClientIp() ?? 'unknown'));
        $attempts = (int) $item->get();

        if ($attempts >= self::MAX_ATTEMPTS) {
            $html = $this->twig->render('admin/login.html.twig', [
                'error' => true,
                'last_username' => (string) $request->request->get('email', ''),
                'throttle_message' => \sprintf(
                    'Trop de tentatives échouées depuis cette adresse. Réessayez dans %d minutes.',
                    (int) ceil(self::WINDOW_SECONDS / 60)
                ),
            ]);
            $event->setResponse(new Response($html, Response::HTTP_TOO_MANY_REQUESTS));
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $ip = $event->getRequest()?->getClientIp() ?? 'unknown';
        $item = $this->cachePool->getItem($this->key($ip));
        $item->set(((int) $item->get()) + 1);
        $item->expiresAfter(self::WINDOW_SECONDS);
        $this->cachePool->save($item);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $ip = $event->getRequest()?->getClientIp() ?? 'unknown';
        $this->cachePool->deleteItem($this->key($ip));
    }

    private function key(string $ip): string
    {
        return 'login_fail_'.sha1($ip);
    }
}
