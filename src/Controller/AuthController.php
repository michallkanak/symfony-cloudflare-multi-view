<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class AuthController
{
    public function __construct(
        private string $dashboardPassword,
        private TwigEnvironment $twig,
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private bool $secureDashboard = true,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ) {
    }

    #[Route('/cloudflare-stats/login', name: 'cf_multi_view_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        // Automatically redirect to dashboard if security is disabled
        if (!$this->secureDashboard) {
            return new RedirectResponse($this->router->generate('cf_multi_view_dashboard'));
        }

        $error = null;

        if ($request->isMethod('POST')) {
            // CSRF Protection
            if ($this->csrfTokenManager) {
                $token = $request->request->get('_csrf_token');
                if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('cf_multi_view_login', (string) $token))) {
                    return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
                }
            }

            // Honeypot check
            if ($request->request->get('email_verify')) {
                // Bot detected
                return new Response('Bot detected.', Response::HTTP_FORBIDDEN);
            }

            $password = $request->request->get('password');
            if ($password === $this->dashboardPassword) {
                $request->getSession()->set('cf_dashboard_authenticated', true);

                return new RedirectResponse($this->router->generate('cf_multi_view_dashboard'));
            }

            $error = $this->translator->trans('auth.error.wrong_password');
        }

        $csrfToken = $this->csrfTokenManager ? $this->csrfTokenManager->getToken('cf_multi_view_login')->getValue() : null;

        $html = $this->twig->render('@CfMultiView/auth/login.html.twig', [
            'error' => $error,
            'csrf_token' => $csrfToken,
        ]);

        return new Response($html);
    }

    #[Route('/cloudflare-stats/logout', name: 'cf_multi_view_logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->remove('cf_dashboard_authenticated');

        return new RedirectResponse($this->router->generate('cf_multi_view_login'));
    }
}
