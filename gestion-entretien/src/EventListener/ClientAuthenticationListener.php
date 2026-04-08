<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

class ClientAuthenticationListener
{
    private const PROTECTED_PREFIXES = [
        '/candidat',
        '/client',
        '/opportunites',
    ];

    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!$this->isProtectedPath($path)) {
            return;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $userId = (int) $session->get('user_id', 0);
        $userRole = (string) $session->get('user_role', '');

        if ($userId <= 0 || $userRole !== 'CLIENT') {
            $signinUrl = $this->router->generate('app_signin');
            $event->setResponse(new RedirectResponse($signinUrl));
        }
    }

    private function isProtectedPath(string $path): bool
    {
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
