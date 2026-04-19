<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Michallkanak\SymfonyCloudflareMultiView\Command\DeleteAccountCommand;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeleteAccountCommandTest extends TestCase
{
    /** @var CfMultiViewDomainRepository&MockObject */
    private $domainRepository;
    /** @var CfMultiViewTrafficStatRepository&MockObject */
    private $statRepository;
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    /** @var TranslatorInterface&MockObject */
    private $translator;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->domainRepository = $this->createMock(CfMultiViewDomainRepository::class);
        $this->statRepository = $this->createMock(CfMultiViewTrafficStatRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $command = new DeleteAccountCommand(
            $this->domainRepository,
            $this->statRepository,
            $this->entityManager,
            $this->translator
        );

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($application->find('cf-multi-view:delete-account'));
    }

    public function testExecuteAbortsIfAccountNotFound(): void
    {
        $this->domainRepository->method('findBy')->willReturn([]);

        $this->commandTester->execute(['--name' => 'NonExistent']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.delete_account.error.not_found', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteDeletesAccountAndData(): void
    {
        $domain = new CfMultiViewDomain();
        $this->domainRepository->method('findBy')->willReturn([$domain]);

        $this->statRepository->method('countByAccountName')->willReturn(10);
        $this->statRepository->expects($this->once())->method('deleteByAccountName')->with('Personal');

        $this->entityManager->expects($this->once())->method('remove')->with($domain);
        $this->entityManager->expects($this->once())->method('flush');

        $this->commandTester->execute([
            '--name' => 'Personal',
            '--force' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('command.delete_account.success', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }
}
