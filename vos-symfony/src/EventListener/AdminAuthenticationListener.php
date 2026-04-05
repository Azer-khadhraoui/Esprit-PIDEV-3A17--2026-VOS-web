<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;

class AdminAuthenticationListener
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Vérifier si la route commence par /admin
        if (!str_starts_with($path, '/admin')) {
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
            // Rediriger vers signin
            $signinUrl = $this->router->generate('app_signin');
            $response = new RedirectResponse($signinUrl);
            $event->setResponse($response);
        }
    }
}
