<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Tests\Entity;

use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewDomain;
use Michallkanak\SymfonyCloudflareMultiView\Entity\CfMultiViewTrafficStat;
use PHPUnit\Framework\TestCase;

class EntityTest extends TestCase
{
    public function testDomainEntity(): void
    {
        $domain = new CfMultiViewDomain();
        $domain->setZoneId('zone-123')
               ->setName('example.com')
               ->setAccountName('Personal')
               ->setIsActive(false);

        $this->assertNull($domain->getId());
        $this->assertEquals('zone-123', $domain->getZoneId());
        $this->assertEquals('example.com', $domain->getName());
        $this->assertEquals('Personal', $domain->getAccountName());
        $this->assertFalse($domain->isActive());
    }

    public function testTrafficStatEntity(): void
    {
        $domain = new CfMultiViewDomain();
        $timestamp = new \DateTime('2026-04-19 10:00:00');

        $stat = new CfMultiViewTrafficStat();
        $stat->setDomain($domain)
             ->setTimestamp($timestamp)
             ->setPeriod('1h')
             ->setUniqueVisitors(100)
             ->setTotalRequests(500)
             ->setBandwidth('1024')
             ->setThreats(5)
             ->setTopCountries(['PL' => 50, 'US' => 30]);

        $this->assertNull($stat->getId());
        $this->assertSame($domain, $stat->getDomain());
        $this->assertSame($timestamp, $stat->getTimestamp());
        $this->assertEquals('1h', $stat->getPeriod());
        $this->assertEquals(100, $stat->getUniqueVisitors());
        $this->assertEquals(500, $stat->getTotalRequests());
        $this->assertEquals('1024', $stat->getBandwidth());
        $this->assertEquals(5, $stat->getThreats());
        $this->assertEquals(['PL' => 50, 'US' => 30], $stat->getTopCountries());
    }
}
