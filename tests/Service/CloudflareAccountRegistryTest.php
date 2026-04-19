<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Service;

use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareAccountRegistry;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareGraphQLClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CloudflareAccountRegistryTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    public function testInitializationAndGetters(): void
    {
        $accounts = [
            ['name' => 'Account 1', 'token' => 'token1'],
            ['name' => 'Account 2', 'token' => 'token2'],
        ];

        $registry = new CloudflareAccountRegistry($accounts, $this->httpClient, $this->translator);

        $this->assertEquals(['Account 1', 'Account 2'], $registry->getAccountNames());
        $this->assertEquals($accounts, $registry->getAccountsConfig());

        $client1 = $registry->getClient('Account 1');
        $this->assertInstanceOf(CloudflareGraphQLClient::class, $client1);

        $all = $registry->getAll();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('Account 1', $all);
        $this->assertArrayHasKey('Account 2', $all);
    }

    public function testGetClientThrowsExceptionOnMissingAccount(): void
    {
        $registry = new CloudflareAccountRegistry([], $this->httpClient, $this->translator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No Cloudflare account configured with name "Missing".');

        $registry->getClient('Missing');
    }
}
