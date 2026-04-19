<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Michallkanak\SymfonyCloudflareMultiView\Command\SyncStatsCommand;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewTrafficStat;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareAccountRegistry;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareGraphQLClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class SyncStatsCommandTest extends TestCase
{
    /** @var CloudflareAccountRegistry&MockObject */
    private $accountRegistry;
    /** @var CfMultiViewDomainRepository&MockObject */
    private $domainRepository;
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    /** @var TranslatorInterface&MockObject */
    private $translator;
    /** @var CloudflareGraphQLClient&MockObject */
    private $client;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->accountRegistry = $this->createMock(CloudflareAccountRegistry::class);
        $this->domainRepository = $this->createMock(CfMultiViewDomainRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->client = $this->createMock(CloudflareGraphQLClient::class);

        $this->translator->method('trans')->willReturnArgument(0);

        $command = new SyncStatsCommand(
            $this->accountRegistry,
            $this->domainRepository,
            $this->entityManager,
            $this->translator
        );

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($application->find('cf-multi-view:sync-stats'));
    }

    public function testExecuteSuccess(): void
    {
        $this->accountRegistry->method('getAccountNames')->willReturn(['Account 1']);
        $this->accountRegistry->method('getClient')->with('Account 1')->willReturn($this->client);

        $domain = new CfMultiViewDomain();
        $domain->setZoneId('zone-1')->setAccountName('Account 1');

        $this->domainRepository->method('findBy')->willReturn([$domain]);

        $graphQlResponse = [
            'viewer' => [
                'zones' => [
                    [
                        'zoneTag' => 'zone-1',
                        'totals' => [
                            [
                                'dimensions' => ['datetime' => '2026-04-19T10:00:00Z'],
                                'sum' => ['requests' => 100, 'bytes' => 1024, 'threats' => 0],
                                'uniq' => ['uniques' => 50],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->client->method('queryWithRateInfo')->willReturn([
            'data' => $graphQlResponse,
            'rateLimit' => 100,
            'rateLimitReset' => time() + 3600,
        ]);

        $statRepo = $this->createMock(CfMultiViewTrafficStatRepository::class);
        $statRepo->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->with(CfMultiViewTrafficStat::class)->willReturn($statRepo);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->commandTester->execute([
            '--period' => '1h',
            '--start' => '-2 hours',
            '--end' => 'now',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.sync_stats.success', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithInvalidPeriod(): void
    {
        $this->commandTester->execute(['--period' => 'invalid']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.sync_stats.error.unsupported_period', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
