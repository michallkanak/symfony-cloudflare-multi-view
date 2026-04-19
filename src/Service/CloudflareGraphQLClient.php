<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CloudflareGraphQLClient
{
    private const API_URL = 'https://api.cloudflare.com/client/v4/graphql';
    private const MAX_RETRIES = 3;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $cloudflareToken,
        private TranslatorInterface $translator,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        return $this->queryWithRateInfo($query, $variables)['data'];
    }

    /**
     * Executes a GraphQL query and returns both data and rate limit metadata.
     *
     * The 'rateLimit' field contains X-RateLimit-Remaining (requests left in current window).
     * The 'rateLimitReset' field contains the Unix timestamp when the window resets.
     * Both are set to -1 if the header is not present in the response.
     *
     * @param array<string, mixed> $variables
     *
     * @return array{data: array<string, mixed>, rateLimit: int, rateLimitReset: int}
     */
    public function queryWithRateInfo(string $query, array $variables = []): array
    {
        $retries = 0;
        while ($retries < self::MAX_RETRIES) {
            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'headers' => [
                        'Authorization' => 'Bearer '.trim($this->cloudflareToken),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'query' => $query,
                        'variables' => $variables,
                    ],
                ]);

                $statusCode = $response->getStatusCode();

                if (429 === $statusCode) {
                    $this->handleRateLimit($response->getHeaders(false));
                    ++$retries;
                    continue;
                }

                $headers = $response->getHeaders(false);
                $rateLimit = isset($headers['x-ratelimit-remaining'][0])
                    ? (int) $headers['x-ratelimit-remaining'][0]
                    : -1;
                $rateLimitReset = isset($headers['x-ratelimit-reset'][0])
                    ? (int) $headers['x-ratelimit-reset'][0]
                    : -1;

                $data = $response->toArray();
                if (!empty($data['errors'])) {
                    throw new \RuntimeException('Cloudflare GraphQL Error: '.json_encode($data['errors']));
                }

                return [
                    'data' => $data['data'] ?? [],
                    'rateLimit' => $rateLimit,
                    'rateLimitReset' => $rateLimitReset,
                ];
            } catch (HttpExceptionInterface $e) {
                if (429 === $e->getResponse()->getStatusCode()) {
                    $this->handleRateLimit($e->getResponse()->getHeaders(false));
                    ++$retries;
                    continue;
                }
                throw $e;
            }
        }

        throw new \RuntimeException($this->translator->trans('cloudflare.error.max_retries'));
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function handleRateLimit(array $headers): void
    {
        $retryAfter = (int) ($headers['retry-after'][0] ?? 60);
        if ($this->logger) {
            $this->logger->warning($this->translator->trans('cloudflare.warning.rate_limit', ['%seconds%' => $retryAfter]));
        }
        sleep($retryAfter);
    }
}
