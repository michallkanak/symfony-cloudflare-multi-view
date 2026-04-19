<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Command;

use Doctrine\ORM\EntityManagerInterface;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewTrafficStat;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareAccountRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: 'cf-multi-view:sync-stats',
    description: 'command.sync_stats.description'
)]
class SyncStatsCommand extends Command
{
    /**
     * @var array<string, string>
     */
    private array $periodToNodeMap = [
        '1m' => 'httpRequests1mGroups',
        '1h' => 'httpRequests1hGroups',
        '1d' => 'httpRequests1dGroups',
    ];

    public function __construct(
        private CloudflareAccountRegistry $accountRegistry,
        private CfMultiViewDomainRepository $domainRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('period', null, InputOption::VALUE_OPTIONAL, $this->translator->trans('command.sync_stats.option.period'), '1h')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, $this->translator->trans('command.sync_stats.option.start'), '-25 hours')
            ->addOption('end', null, InputOption::VALUE_OPTIONAL, $this->translator->trans('command.sync_stats.option.end'), 'now')
            ->addOption('with-countries', null, InputOption::VALUE_NONE, $this->translator->trans('command.sync_stats.option.with_countries'))
            ->addOption('account', null, InputOption::VALUE_OPTIONAL, $this->translator->trans('command.sync_stats.option.account'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $period = $input->getOption('period');
        $withCountries = $input->getOption('with-countries');
        $filterAccount = $input->getOption('account');

        if (!isset($this->periodToNodeMap[$period])) {
            $io->error($this->translator->trans('command.sync_stats.error.unsupported_period', ['%period%' => $period]));

            return Command::FAILURE;
        }

        $nodeName = $this->periodToNodeMap[$period];
        $startStr = $input->getOption('start');
        $endStr = $input->getOption('end');

        try {
            $utc = new \DateTimeZone('UTC');
            $dtStart = new \DateTime($startStr, $utc);
            $dtEnd = new \DateTime($endStr, $utc);
            $dtStart->setTimezone($utc);
            $dtEnd->setTimezone($utc);
        } catch (\Exception $e) {
            $io->error($this->translator->trans('command.sync_stats.error.invalid_date', ['%message%' => $e->getMessage()]));

            return Command::FAILURE;
        }

        $datetimeStart = $dtStart->format('Y-m-d\TH:i:s\Z');
        $datetimeEnd = $dtEnd->format('Y-m-d\TH:i:s\Z');

        // Get all account names to process
        $accountNames = $this->accountRegistry->getAccountNames();
        if (null !== $filterAccount) {
            if (!in_array($filterAccount, $accountNames, true)) {
                $io->error($this->translator->trans('command.sync_stats.error.account_not_found', ['%filterAccount%' => $filterAccount]));

                return Command::FAILURE;
            }
            $accountNames = [$filterAccount];
        }

        $totalSaved = 0;

        foreach ($accountNames as $accountName) {
            $client = $this->accountRegistry->getClient($accountName);
            $domains = $this->domainRepository->findBy(['accountName' => $accountName, 'isActive' => true]);

            if (empty($domains)) {
                $io->warning($this->translator->trans('command.sync_stats.warning.no_domains', ['%accountName%' => $accountName]));
                continue;
            }

            $io->section($this->translator->trans('command.common.account_with_domains_label', [
                '%accountName%' => $accountName,
                '%count%' => count($domains),
            ]));

            $io->info($this->translator->trans('command.sync_stats.info.starting', [
                '%count%' => count($domains),
                '%start%' => $datetimeStart,
                '%end%' => $datetimeEnd,
            ]));

            // Split into smaller chunks due to Cloudflare API limits
            $domainChunks = array_chunk($domains, 2);
            $totalChunks = count($domainChunks);

            foreach ($domainChunks as $index => $chunk) {
                /** @var CfMultiViewDomain[] $chunk */
                $zoneMap = [];
                $zoneTags = [];
                $rateLimitRemaining = -1;
                $rateLimitReset = -1;

                foreach ($chunk as $domain) {
                    $zoneMap[$domain->getZoneId()] = $domain;
                    $zoneTags[] = $domain->getZoneId();
                }

                $countryMapPart = $withCountries ? ', countryMap { clientCountryName, requests }' : '';

                $query = <<<GQL
query (\$zoneTags: [String!], \$datetimeStart: String!, \$datetimeEnd: String!) {
  viewer {
    zones(filter: {zoneTag_in: \$zoneTags}) {
      zoneTag
      totals: $nodeName(limit: 10000, filter: {datetime_geq: \$datetimeStart, datetime_leq: \$datetimeEnd}) {
        dimensions { datetime }
        sum { requests, bytes, threats $countryMapPart }
        uniq { uniques }
      }
    }
  }
}
GQL;
                $variables = [
                    'zoneTags' => $zoneTags,
                    'datetimeStart' => $datetimeStart,
                    'datetimeEnd' => $datetimeEnd,
                ];

                try {
                    $queryResult = $client->queryWithRateInfo($query, $variables);
                    $result = $queryResult['data'];
                    $rateLimitRemaining = $queryResult['rateLimit'];
                    $rateLimitReset = $queryResult['rateLimitReset'];
                } catch (\Exception $e) {
                    $io->error($this->translator->trans('command.sync_stats.error.cloudflare', ['%message%' => $e->getMessage()]));
                    continue;
                }

                $zonesData = $result['viewer']['zones'] ?? [];

                foreach ($zonesData as $zoneData) {
                    $zoneTag = $zoneData['zoneTag'] ?? null;
                    $groups = $zoneData['totals'] ?? [];

                    if (!$zoneTag || empty($groups)) {
                        continue;
                    }

                    $domain = $zoneMap[$zoneTag] ?? null;
                    if (!$domain) {
                        continue;
                    }

                    foreach ($groups as $group) {
                        $recordDatetime = $group['dimensions']['datetime'] ?? null;
                        if (!$recordDatetime) {
                            continue;
                        }

                        try {
                            $timestamp = new \DateTime($recordDatetime);
                        } catch (\Exception $e) {
                            continue;
                        }

                        $existing = $this->entityManager->getRepository(CfMultiViewTrafficStat::class)
                            ->findOneBy([
                                'domain' => $domain,
                                'timestamp' => $timestamp,
                                'period' => $period,
                            ]);

                        if (!$existing) {
                            $stat = new CfMultiViewTrafficStat();
                            $stat->setDomain($domain);
                            $stat->setTimestamp($timestamp);
                            $stat->setPeriod($period);
                        } else {
                            $stat = $existing;
                        }

                        $stat->setTotalRequests((int) ($group['sum']['requests'] ?? 0));
                        $stat->setBandwidth((string) ($group['sum']['bytes'] ?? 0));
                        $stat->setThreats((int) ($group['sum']['threats'] ?? 0));
                        $stat->setUniqueVisitors((int) ($group['uniq']['uniques'] ?? 0));

                        if ($withCountries && !empty($group['sum']['countryMap'])) {
                            $countries = [];
                            foreach ($group['sum']['countryMap'] as $cm) {
                                $code = $cm['clientCountryName'] ?? '??';
                                $countries[$code] = (int) ($cm['requests'] ?? 0);
                            }
                            arsort($countries);
                            $top3 = array_slice($countries, 0, 3, true);
                            $stat->setTopCountries($top3);
                        }

                        $stat->setUpdatedAt(new \DateTime());

                        $this->entityManager->persist($stat);
                        ++$totalSaved;
                    }
                }

                // Flush after each chunk
                $this->entityManager->flush();

                // Adaptive throttling based on X-RateLimit-Remaining header.
                // This avoids hardcoded delays and adjusts to the actual API load.
                if ($index < $totalChunks - 1) {
                    if ($rateLimitRemaining < 0) {
                        // Header not available — fall back to conservative delay
                        sleep(2);
                    } elseif ($rateLimitRemaining < 5) {
                        // Nearly exhausted — wait until the rate limit window resets
                        $waitSeconds = $rateLimitReset > 0
                            ? max(1, $rateLimitReset - time())
                            : 60;
                        if ($this->logger) {
                            $this->logger->warning(sprintf(
                                'Rate limit nearly exhausted (%d remaining). Waiting %ds for reset.',
                                $rateLimitRemaining,
                                $waitSeconds
                            ));
                        }
                        sleep($waitSeconds);
                    } elseif ($rateLimitRemaining < 20) {
                        // Low — throttle to 1 second
                        sleep(1);
                    } elseif ($rateLimitRemaining < 50) {
                        // Moderate — small 500ms pause
                        usleep(500_000);
                    }
                    // Above 50 remaining: no delay needed
                }
            }
        }

        $io->success($this->translator->trans('command.sync_stats.success', ['%total%' => $totalSaved]));

        return Command::SUCCESS;
    }
}
