<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Michallkanak\SymfonyCloudflareMultiView\Command\PurgeStatsCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class PurgeStatsCommandTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    /** @var TranslatorInterface&MockObject */
    private $translator;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $command = new PurgeStatsCommand($this->entityManager, $this->translator);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($application->find('cf-multi-view:purge-stats'));
    }

    public function testExecuteAbortsWhenNoRecordsFound(): void
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.purge_stats.success.no_records', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteDeletesRecords(): void
    {
        // Mock count query
        $countQuery = $this->createMock(AbstractQuery::class);
        $countQuery->method('getSingleScalarResult')->willReturn(5);

        // Mock delete query
        $deleteQuery = $this->createMock(AbstractQuery::class);
        $deleteQuery->method('execute')->willReturn(5);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('delete')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturnOnConsecutiveCalls($countQuery, $deleteQuery);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);

        $this->commandTester->execute([
            '--older-than' => '30 days',
            '--force' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.purge_stats.success.deleted', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteReturnsFailureOnInvalidDate(): void
    {
        $this->commandTester->execute([
            '--older-than' => 'invalid-date',
            '--force' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.purge_stats.error.invalid_date', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
