<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;

/**
 * @extends ServiceEntityRepository<CfMultiViewDomain>
 *
 * @method CfMultiViewDomain|null find($id, $lockMode = null, $lockVersion = null)
 * @method CfMultiViewDomain|null findOneBy(array $criteria, array $orderBy = null)
 * @method CfMultiViewDomain[]    findAll()
 * @method CfMultiViewDomain[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CfMultiViewDomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CfMultiViewDomain::class);
    }

    /**
     * @return CfMultiViewDomain[]
     */
    public function findActiveDomains(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.isActive = :val')
            ->setParameter('val', true)
            ->getQuery()
            ->getResult()
        ;
    }
}
