<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Command;

use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'cf-multi-view:purge-stats',
    description: 'command.purge_stats.description'
)]
class PurgeStatsCommand extends Command
{
    public function __construct(
        private CfMultiViewTrafficStatRepository $statRepository,
        private TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                $this->translator->trans('command.purge_stats.option.older_than'),
                '90 days'
            )
            ->addOption('force', null, InputOption::VALUE_NONE, $this->translator->trans('command.purge_stats.option.force'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $olderThan = $input->getOption('older-than');
        $force = $input->getOption('force');

        try {
            $cutoff = new \DateTime('-'.$olderThan);
        } catch (\Exception $e) {
            $io->error($this->translator->trans('command.purge_stats.error.invalid_date', ['%olderThan%' => $olderThan]));

            return Command::FAILURE;
        }

        // Count records to be deleted
        $count = $this->statRepository->countOlderThan($cutoff);

        if (0 === $count) {
            $io->success($this->translator->trans('command.purge_stats.success.no_records', ['%olderThan%' => $olderThan]));

            return Command::SUCCESS;
        }

        $io->warning($this->translator->trans('command.purge_stats.warning.confirmation', [
            '%count%' => $count,
            '%olderThan%' => $olderThan,
            '%cutoff%' => $cutoff->format('Y-m-d H:i:s'),
        ]));

        if (!$force && !$io->confirm($this->translator->trans('command.purge_stats.confirm_question'), false)) {
            $io->info($this->translator->trans('command.purge_stats.info.aborted'));

            return Command::SUCCESS;
        }

        $deleted = $this->statRepository->deleteOlderThan($cutoff);

        $io->success($this->translator->trans('command.purge_stats.success.deleted', [
            '%deleted%' => $deleted,
            '%olderThan%' => $olderThan,
        ]));

        return Command::SUCCESS;
    }
}
