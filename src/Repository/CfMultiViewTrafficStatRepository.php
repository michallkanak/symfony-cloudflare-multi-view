<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewTrafficStat;

/**
 * @extends ServiceEntityRepository<CfMultiViewTrafficStat>
 *
 * @method CfMultiViewTrafficStat|null find($id, $lockMode = null, $lockVersion = null)
 * @method CfMultiViewTrafficStat|null findOneBy(array $criteria, array $orderBy = null)
 * @method CfMultiViewTrafficStat[]    findAll()
 * @method CfMultiViewTrafficStat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CfMultiViewTrafficStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CfMultiViewTrafficStat::class);
    }

    /**
     * @param string $period e.g. "15m", "1h", "24h"
     *
     * @return CfMultiViewTrafficStat[]
     */
    public function findStatsByPeriodAndDateRange(string $period, \DateTimeInterface $fromDate, \DateTimeInterface $toDate): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('d')
            ->join('s.domain', 'd')
            ->andWhere('s.period = :period')
            ->andWhere('s.timestamp >= :fromDate')
            ->andWhere('s.timestamp <= :toDate')
            ->setParameter('period', $period)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->orderBy('d.accountName', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->addOrderBy('s.timestamp', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function getLatestLogTimestamp(): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('s')
            ->where('s.updatedAt IS NOT NULL')
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result?->getUpdatedAt();
    }
}
