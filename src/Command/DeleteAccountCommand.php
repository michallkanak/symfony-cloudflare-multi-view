<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Command;

use Doctrine\ORM\EntityManagerInterface;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'cf-multi-view:delete-account',
    description: 'command.delete_account.description'
)]
class DeleteAccountCommand extends Command
{
    public function __construct(
        private CfMultiViewDomainRepository $domainRepository,
        private CfMultiViewTrafficStatRepository $statRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, $this->translator->trans('command.delete_account.option.name'))
            ->addOption('force', null, InputOption::VALUE_NONE, $this->translator->trans('command.delete_account.option.force'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accountName = $input->getOption('name');
        $force = $input->getOption('force');

        if (!$accountName) {
            $io->error($this->translator->trans('command.delete_account.error.missing_name'));

            return Command::FAILURE;
        }

        $domains = $this->domainRepository->findBy(['accountName' => $accountName]);

        if (empty($domains)) {
            $io->error($this->translator->trans('command.delete_account.error.not_found', ['%accountName%' => $accountName]));

            return Command::FAILURE;
        }

        $domainCount = count($domains);

        // Count total stats to be removed
        $statsCount = $this->statRepository->countByAccountName($accountName);

        $io->warning($this->translator->trans('command.delete_account.warning.confirmation', [
            '%accountName%' => $accountName,
            '%domainCount%' => $domainCount,
            '%statsCount%' => $statsCount,
        ]));

        if (!$force && !$io->confirm($this->translator->trans('command.purge_stats.confirm_question'), false)) {
            $io->info($this->translator->trans('command.purge_stats.info.aborted'));

            return Command::SUCCESS;
        }

        // Delete all stats for this account's domains first (FK constraint)
        $this->statRepository->deleteByAccountName($accountName);

        // Delete all domains for this account
        foreach ($domains as $domain) {
            $this->entityManager->remove($domain);
        }
        $this->entityManager->flush();

        $io->success($this->translator->trans('command.delete_account.success', [
            '%accountName%' => $accountName,
            '%domainCount%' => $domainCount,
            '%statsCount%' => $statsCount,
        ]));

        return Command::SUCCESS;
    }
}
