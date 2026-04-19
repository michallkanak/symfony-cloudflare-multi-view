<?php

namespace Michallkanak\SymfonyCloudflareMultiView\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

#[AsEventListener(event: KernelEvents::REQUEST)]
class CfMultiViewAuthListener
{
    public function __construct(
        private RouterInterface $router,
        private bool $secureDashboard = true,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // If secure_dashboard is false, allow access without a password
        if (!$this->secureDashboard) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Only protect dashboard route (not the login route itself)
        if ('cf_multi_view_dashboard' === $route) {
            try {
                $session = $request->getSession();
            } catch (\Exception $e) {
                // No session available (e.g. CLI or misconfigured framework)
                return;
            }

            if (!$session->get('cf_dashboard_authenticated', false)) {
                $event->setResponse(new RedirectResponse($this->router->generate('cf_multi_view_login')));
            }
        }
    }
}
