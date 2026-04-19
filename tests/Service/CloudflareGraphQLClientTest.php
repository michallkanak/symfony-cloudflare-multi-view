<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Service;

use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareGraphQLClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CloudflareGraphQLClientTest extends TestCase
{
    /** @var HttpClientInterface&MockObject */
    private $httpClient;
    /** @var TranslatorInterface&MockObject */
    private $translator;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
    }

    public function testQueryReturnsDataSuccessfully(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'data' => ['viewer' => ['zones' => []]],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $client = new CloudflareGraphQLClient($this->httpClient, 'fake-token', $this->translator);
        $result = $client->query('query {}');

        $this->assertArrayHasKey('viewer', $result);
    }

    public function testQueryThrowsExceptionOnGraphqlError(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'errors' => [['message' => 'GraphQL parse error']],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $client = new CloudflareGraphQLClient($this->httpClient, 'fake-token', $this->translator);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloudflare GraphQL Error');

        $client->query('query {}');
    }

    public function testRateLimitTriggersSleepAndRetry(): void
    {
        $rateLimitResponse = $this->createMock(ResponseInterface::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);
        $rateLimitResponse->method('getHeaders')->willReturn(['retry-after' => ['0']]); // '0' seconds so the test does not sleep

        $successResponse = $this->createMock(ResponseInterface::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('toArray')->willReturn([
            'data' => ['ok' => true],
        ]);

        // First time 429, second time 200
        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($rateLimitResponse, $successResponse);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $client = new CloudflareGraphQLClient($this->httpClient, 'fake-token', $this->translator, $logger);
        $result = $client->query('query {}');

        $this->assertTrue($result['ok']);
    }
}
