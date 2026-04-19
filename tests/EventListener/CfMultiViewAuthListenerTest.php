<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\EventListener;

use Michallkanak\SymfonyCloudflareMultiView\EventListener\CfMultiViewAuthListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;

class CfMultiViewAuthListenerTest extends TestCase
{
    private RouterInterface $router;
    private CfMultiViewAuthListener $listener;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('generate')
             ->with('cf_multi_view_login')
             ->willReturn('/cloudflare-stats/login');

        $this->listener = new CfMultiViewAuthListener($this->router);
    }

    public function testDoesNotRedirectWhenNotDashboardPath(): void
    {
        $request = Request::create('/other-path');
        $request->attributes->set('_route', 'some_other_route');
        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testRedirectsWhenNoSessionAuthOnDashboard(): void
    {
        $request = Request::create('/cloudflare-stats/view');
        $request->attributes->set('_route', 'cf_multi_view_dashboard');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/cloudflare-stats/login', $response->getTargetUrl());
    }

    public function testDoesNotRedirectWhenAuthenticated(): void
    {
        $request = Request::create('/cloudflare-stats/view');
        $request->attributes->set('_route', 'cf_multi_view_dashboard');
        $session = new Session(new MockArraySessionStorage());
        $session->set('cf_dashboard_authenticated', true);
        $request->setSession($session);

        $event = $this->createRequestEvent($request);

        $this->listener->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        // Backwards compatibility with Symfony 5.4 - HttpKernelInterface::MAIN_REQUEST
        $requestType = defined(HttpKernelInterface::class.'::MAIN_REQUEST')
            ? HttpKernelInterface::MAIN_REQUEST
            : 1;

        return new RequestEvent($kernel, $request, $requestType);
    }
}
