<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Command;

use Michallkanak\SymfonyCloudflareMultiView\Command\PurgeStatsCommand;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class PurgeStatsCommandTest extends TestCase
{
    /** @var CfMultiViewTrafficStatRepository&MockObject */
    private $statRepository;
    /** @var TranslatorInterface&MockObject */
    private $translator;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->statRepository = $this->createMock(CfMultiViewTrafficStatRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $command = new PurgeStatsCommand($this->statRepository, $this->translator);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($application->find('cf-multi-view:purge-stats'));
    }

    public function testExecuteAbortsWhenNoRecordsFound(): void
    {
        $this->statRepository->method('countOlderThan')->willReturn(0);

        $this->commandTester->execute(['--force' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.purge_stats.success.no_records', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteDeletesRecords(): void
    {
        $this->statRepository->method('countOlderThan')->willReturn(5);
        $this->statRepository->method('deleteOlderThan')->willReturn(5);

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
