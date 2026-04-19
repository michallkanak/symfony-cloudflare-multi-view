<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Command;

use Doctrine\ORM\EntityManagerInterface;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareAccountRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'cf-multi-view:fetch-domains',
    description: 'command.fetch_domains.description'
)]
class FetchDomainsCommand extends Command
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CloudflareAccountRegistry $accountRegistry,
        private CfMultiViewDomainRepository $domainRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('account', null, InputOption::VALUE_OPTIONAL, $this->translator->trans('command.sync_stats.option.account'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->translator->trans('command.fetch_domains.title'));

        $filterAccount = $input->getOption('account');
        $accountsConfig = $this->accountRegistry->getAccountsConfig();

        if (null !== $filterAccount) {
            $accountsConfig = array_values(array_filter(
                $accountsConfig,
                fn (array $a) => $a['name'] === $filterAccount
            ));

            if (empty($accountsConfig)) {
                $io->error($this->translator->trans('command.fetch_domains.error.account_not_found', ['%filterAccount%' => $filterAccount]));

                return Command::FAILURE;
            }
        }

        $totalSaved = 0;
        $totalUpdated = 0;
        $totalDeactivated = 0;

        foreach ($accountsConfig as $account) {
            $accountName = $account['name'];
            $token = $account['token'];

            $io->section($this->translator->trans('command.common.account_label', ['%accountName%' => $accountName]));

            $fetchedZoneIds = [];
            $page = 1;
            $perPage = 30;

            do {
                $io->info($this->translator->trans('command.fetch_domains.info.fetching_page', ['%page%' => $page]));

                $response = $this->httpClient->request('GET', 'https://api.cloudflare.com/client/v4/zones', [
                    'headers' => [
                        'Authorization' => 'Bearer '.trim($token),
                        'Content-Type' => 'application/json',
                    ],
                    'query' => [
                        'page' => $page,
                        'per_page' => $perPage,
                    ],
                ]);

                if (200 !== $response->getStatusCode()) {
                    $errorContent = $response->getContent(false);
                    $io->error($this->translator->trans('command.fetch_domains.error.api', [
                        '%status%' => $response->getStatusCode(),
                        '%details%' => $errorContent,
                        '%token%' => substr($token, 0, 8).'...',
                    ]));
                    continue 2;
                }

                $data = $response->toArray();
                $zones = $data['result'] ?? [];

                if (empty($zones)) {
                    break;
                }

                foreach ($zones as $zone) {
                    $zoneId = $zone['id'];
                    $name = $zone['name'];
                    $fetchedZoneIds[] = $zoneId;

                    $domain = $this->domainRepository->findOneBy([
                        'zoneId' => $zoneId,
                        'accountName' => $accountName,
                    ]);

                    if (!$domain) {
                        $domain = new CfMultiViewDomain();
                        $domain->setZoneId($zoneId);
                        $domain->setName($name);
                        $domain->setAccountName($accountName);
                        $domain->setIsActive(true);
                        $this->entityManager->persist($domain);
                        ++$totalSaved;
                    } else {
                        if ($domain->getName() !== $name) {
                            $domain->setName($name);
                            ++$totalUpdated;
                        }
                        // Reactivate if it was deactivated
                        if (!$domain->isActive()) {
                            $domain->setIsActive(true);
                            ++$totalUpdated;
                        }
                    }
                }

                $this->entityManager->flush();

                $totalPages = $data['result_info']['total_pages'] ?? 1;
                ++$page;
            } while ($page <= $totalPages);

            // Deactivate domains that belong to this account but were NOT returned by the API
            $existingDomains = $this->domainRepository->findBy(['accountName' => $accountName, 'isActive' => true]);
            foreach ($existingDomains as $existing) {
                if (!in_array($existing->getZoneId(), $fetchedZoneIds, true)) {
                    $existing->setIsActive(false);
                    ++$totalDeactivated;
                }
            }

            $this->entityManager->flush();
        }

        $io->success($this->translator->trans('command.fetch_domains.success', [
            '%added%' => $totalSaved,
            '%updated%' => $totalUpdated,
        ]));

        if ($totalDeactivated > 0) {
            $io->warning($this->translator->trans('command.fetch_domains.warning.deactivated', ['%count%' => $totalDeactivated]));
        }

        return Command::SUCCESS;
    }
}
