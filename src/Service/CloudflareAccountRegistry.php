<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Registry holding one CloudflareGraphQLClient per configured account.
 *
 * @phpstan-type AccountConfig array{name: string, token: string}
 */
class CloudflareAccountRegistry
{
    /** @var CloudflareGraphQLClient[] keyed by account name */
    private array $clients = [];

    /**
     * @param array<int, AccountConfig> $accounts
     */
    public function __construct(
        private array $accounts,
        private HttpClientInterface $httpClient,
        private TranslatorInterface $translator,
        private ?LoggerInterface $logger = null,
    ) {
        foreach ($accounts as $account) {
            $this->clients[$account['name']] = new CloudflareGraphQLClient(
                $this->httpClient,
                $account['token'],
                $this->translator,
                $this->logger,
            );
        }
    }

    /**
     * Returns the GraphQL client for a given account name.
     */
    public function getClient(string $accountName): CloudflareGraphQLClient
    {
        if (!isset($this->clients[$accountName])) {
            throw new \InvalidArgumentException(sprintf('No Cloudflare account configured with name "%s".', $accountName));
        }

        return $this->clients[$accountName];
    }

    /**
     * Returns all account names.
     *
     * @return string[]
     */
    public function getAccountNames(): array
    {
        return array_keys($this->clients);
    }

    /**
     * Returns all clients keyed by account name.
     *
     * @return CloudflareGraphQLClient[]
     */
    public function getAll(): array
    {
        return $this->clients;
    }

    /**
     * Returns raw account configuration (name + token).
     *
     * @return array<int, AccountConfig>
     */
    public function getAccountsConfig(): array
    {
        return $this->accounts;
    }
}
