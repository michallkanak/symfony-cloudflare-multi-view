<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Michallkanak\SymfonyCloudflareMultiView\Command\FetchDomainsCommand;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareAccountRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class FetchDomainsCommandTest extends TestCase
{
    private MockHttpClient $httpClient;
    /** @var CloudflareAccountRegistry&MockObject */
    private $accountRegistry;
    /** @var CfMultiViewDomainRepository&MockObject */
    private $domainRepository;
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    /** @var TranslatorInterface&MockObject */
    private $translator;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->accountRegistry = $this->createMock(CloudflareAccountRegistry::class);
        $this->domainRepository = $this->createMock(CfMultiViewDomainRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->translator->method('trans')->willReturnArgument(0);

        $command = new FetchDomainsCommand(
            $this->httpClient,
            $this->accountRegistry,
            $this->domainRepository,
            $this->entityManager,
            $this->translator
        );

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($application->find('cf-multi-view:fetch-domains'));
    }

    public function testExecuteSuccess(): void
    {
        $this->accountRegistry->method('getAccountsConfig')->willReturn([
            ['name' => 'Account 1', 'token' => 'token1'],
        ]);

        $responseData = [
            'result' => [
                ['id' => 'zone-1', 'name' => 'example.com'],
                ['id' => 'zone-2', 'name' => 'example.org'],
            ],
            'result_info' => [
                'total_pages' => 1,
            ],
        ];

        $json = json_encode($responseData);
        $this->httpClient->setResponseFactory(new MockResponse($json ?: '', ['http_code' => 200]));

        $this->domainRepository->method('findOneBy')->willReturn(null);
        $this->domainRepository->method('findBy')->willReturn([]);

        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.fetch_domains.success', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithMissingAccount(): void
    {
        $this->accountRegistry->method('getAccountsConfig')->willReturn([
            ['name' => 'Existing', 'token' => 'token'],
        ]);

        $this->commandTester->execute(['--account' => 'NonExistent']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.fetch_domains.error.account_not_found', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
