<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

class AdminAuthenticationListener
{
    private const PROTECTED_PREFIXES = [
        '/admin',
        '/gestion-offre',
        '/gestion-entretien',
        '/evaluation/entretien',
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

        // Vérifier la session admin
        $session = $request->getSession();
        
        if (!$session->isStarted()) {
            $session->start();
        }

        $adminId = $session->get('admin_user_id');
        $adminRole = $session->get('admin_user_role');

        if (!$adminId || !$adminRole || !str_starts_with((string) $adminRole, 'ADMIN')) {
            $signinUrl = $this->router->generate('app_signin');
            $response = new RedirectResponse($signinUrl);
            $event->setResponse($response);
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
