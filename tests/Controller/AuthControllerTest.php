<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Controller;

use Michallkanak\SymfonyCloudflareMultiView\Controller\AuthController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class AuthControllerTest extends TestCase
{
    private TwigEnvironment&MockObject $twig;
    private RouterInterface&MockObject $router;
    private TranslatorInterface&MockObject $translator;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(TwigEnvironment::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    public function testRedirectsToDashboardWhenSecurityDisabled(): void
    {
        $this->router->method('generate')
            ->with('cf_multi_view_dashboard')
            ->willReturn('/dashboard');

        $controller = new AuthController(
            'password',
            $this->twig,
            $this->router,
            $this->translator,
            false // secureDashboard = false
        );

        $response = $controller->login(new Request());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/dashboard', $response->getTargetUrl());
    }

    public function testShowsLoginFormWhenSecurityEnabled(): void
    {
        $this->twig->method('render')
            ->willReturn('<html>Login Form</html>');

        $controller = new AuthController(
            'password',
            $this->twig,
            $this->router,
            $this->translator,
            true // secureDashboard = true
        );

        $response = $controller->login(new Request());

        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
