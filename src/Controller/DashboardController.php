<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Controller;

use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;
use Michallkanak\SymfonyCloudflareMultiView\Service\CloudflareAccountRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class DashboardController
{
    public function __construct(
        private CfMultiViewTrafficStatRepository $statRepository,
        private CfMultiViewDomainRepository $domainRepository,
        private CloudflareAccountRegistry $accountRegistry,
        private TwigEnvironment $twig,
        private TranslatorInterface $translator,
        private string $displayTimezone = 'UTC',
        private bool $secureDashboard = true,
    ) {
    }

    #[Route('/cloudflare-stats', name: 'cf_multi_view_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $range = $request->query->get('range', '6h');

        $hours = match ($range) {
            '7d' => 168,
            '48h' => 48,
            '24h' => 24,
            '12h' => 12,
            '6h' => 6,
            '3h' => 3,
            '1h' => 1,
            default => 1,
        };

        // We work with UTC for database/external API consistency
        $utc = new \DateTimeZone('UTC');
        $toDate = new \DateTime('now', $utc);
        $fromDate = (clone $toDate)->modify("-{$hours} hours");

        // Step 1: Pre-populate all active domains with zero stats.
        // Groups are initialized in the order defined by the 'accounts' config
        // so the dashboard always shows them in the same, user-defined order.
        $domainGroups = [];

        // Initialize groups in YAML config order
        $configuredAccountNames = $this->accountRegistry->getAccountNames();
        foreach ($configuredAccountNames as $accountName) {
            $domainGroups[$accountName] = [];
        }

        // Fill in all active domains (with zero stats as placeholder)
        $allDomains = $this->domainRepository->findActiveDomains();
        foreach ($allDomains as $dmn) {
            $groupName = $dmn->getAccountName() ?: $this->translator->trans('dashboard.group.others');
            $dmnName = $dmn->getName();

            // If account not in config (e.g. deleted), append at the end
            if (!isset($domainGroups[$groupName])) {
                $domainGroups[$groupName] = [];
            }

            $domainGroups[$groupName][$dmnName] = [
                'uniqueVisitors' => 0,
                'totalRequests' => 0,
                'bandwidth' => 0,
                'threats' => 0,
                'history' => [],
            ];
        }

        // Remove empty groups (accounts with no domains)
        $domainGroups = array_filter($domainGroups, fn ($domains) => !empty($domains));

        // Step 2: Overlay actual statistics on top of the pre-populated structure
        $stats = $this->statRepository->findStatsByPeriodAndDateRange('1h', $fromDate, $toDate);
        $displayTz = new \DateTimeZone($this->displayTimezone);
        $labelFormat = $hours > 48 ? 'd.m H:i' : 'H:i';

        foreach ($stats as $stat) {
            $dmn = $stat->getDomain();
            if (null === $dmn) {
                continue;
            }

            $groupName = $dmn->getAccountName() ?: $this->translator->trans('dashboard.group.others');
            $dmnName = $dmn->getName();

            // Ensure the group/domain exists (may have been added between requests)
            if (!isset($domainGroups[$groupName][$dmnName])) {
                $domainGroups[$groupName][$dmnName] = [
                    'uniqueVisitors' => 0,
                    'totalRequests' => 0,
                    'bandwidth' => 0,
                    'threats' => 0,
                    'history' => [],
                ];
            }

            $current = $domainGroups[$groupName][$dmnName];
            $domainGroups[$groupName][$dmnName] = [
                'uniqueVisitors' => $current['uniqueVisitors'] + $stat->getUniqueVisitors(),
                'totalRequests' => $current['totalRequests'] + $stat->getTotalRequests(),
                'bandwidth' => $current['bandwidth'] + (int) $stat->getBandwidth(),
                'threats' => $current['threats'] + $stat->getThreats(),
                'history' => $current['history'],
            ];

            $timestamp = $stat->getTimestamp();
            if (!$timestamp) {
                continue;
            }

            $localTime = \DateTime::createFromInterface($timestamp);
            $localTime->setTimezone($displayTz);

            $domainGroups[$groupName][$dmnName]['history'][] = [
                'timestamp' => $timestamp->getTimestamp(),
                'label' => $localTime->format($labelFormat),
                'requests' => $stat->getTotalRequests(),
                'visitors' => $stat->getUniqueVisitors(),
                'topCountries' => $stat->getTopCountries(),
            ];
        }

        // Sort history chronologically by timestamp for each domain
        foreach ($domainGroups as &$domains) {
            foreach ($domains as &$data) {
                usort($data['history'], fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);
            }
        }
        unset($domains, $data);

        // Convert dates to display timezone for the template
        $displayTz = new \DateTimeZone($this->displayTimezone);
        $displayFrom = (clone $fromDate)->setTimezone($displayTz);
        $displayTo = (clone $toDate)->setTimezone($displayTz);

        $latestLog = $this->statRepository->getLatestLogTimestamp();
        $latestLogDate = $latestLog ? \DateTime::createFromInterface($latestLog)->setTimezone($displayTz)->format('Y-m-d H:i') : null;

        $html = $this->twig->render('@CfMultiView/dashboard/index.html.twig', [
            'groups' => $domainGroups,
            'fromDate' => $displayFrom->format('Y-m-d H:i'),
            'toDate' => $displayTo->format('Y-m-d H:i'),
            'latestLogDate' => $latestLogDate,
            'currentRange' => $range,
            'timezone' => $this->displayTimezone,
            'secureDashboard' => $this->secureDashboard,
        ]);

        return new Response($html);
    }
}
