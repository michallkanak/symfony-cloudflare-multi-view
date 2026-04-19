<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Michallkanak\SymfonyCloudflareMultiView\Command\DeleteAccountCommand;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeleteAccountCommandTest extends TestCase
{
    /** @var CfMultiViewDomainRepository&MockObject */
    private $domainRepository;
    /** @var EntityManagerInterface&MockObject */
    private $entityManager;
    /** @var TranslatorInterface&MockObject */
    private $translator;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->domainRepository = $this->createMock(CfMultiViewDomainRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->translator->method('trans')->willReturnArgument(0);

        $command = new DeleteAccountCommand($this->domainRepository, $this->entityManager, $this->translator);

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

        // Mock for count query
        $countQuery = $this->createMock(Query::class);
        $countQuery->method('getSingleScalarResult')->willReturn(10);

        // Mock for delete query
        $deleteQuery = $this->createMock(Query::class);
        $deleteQuery->method('execute')->willReturn(10);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('delete')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturnOnConsecutiveCalls($countQuery, $deleteQuery);

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
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
