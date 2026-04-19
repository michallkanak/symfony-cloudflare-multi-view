<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewTrafficStatRepository;

#[ORM\Entity(repositoryClass: CfMultiViewTrafficStatRepository::class)]
#[ORM\Table(name: 'cf_multi_view_traffic_stat')]
#[ORM\UniqueConstraint(name: 'domain_timestamp_period_idx', columns: ['domain_id', 'timestamp', 'period'])]
class CfMultiViewTrafficStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CfMultiViewDomain::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?CfMultiViewDomain $domain = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(length: 50)]
    private ?string $period = null;

    #[ORM\Column]
    private ?int $uniqueVisitors = 0;

    #[ORM\Column]
    private ?int $totalRequests = 0;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $bandwidth = '0';

    #[ORM\Column]
    private ?int $threats = 0;

    /**
     * @var array<string, int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $topCountries = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomain(): ?CfMultiViewDomain
    {
        return $this->domain;
    }

    public function setDomain(?CfMultiViewDomain $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getUniqueVisitors(): ?int
    {
        return $this->uniqueVisitors;
    }

    public function setUniqueVisitors(int $uniqueVisitors): static
    {
        $this->uniqueVisitors = $uniqueVisitors;

        return $this;
    }

    public function getTotalRequests(): ?int
    {
        return $this->totalRequests;
    }

    public function setTotalRequests(int $totalRequests): static
    {
        $this->totalRequests = $totalRequests;

        return $this;
    }

    public function getBandwidth(): ?string
    {
        return $this->bandwidth;
    }

    public function setBandwidth(string $bandwidth): static
    {
        $this->bandwidth = $bandwidth;

        return $this;
    }

    public function getThreats(): ?int
    {
        return $this->threats;
    }

    public function setThreats(int $threats): static
    {
        $this->threats = $threats;

        return $this;
    }

    /**
     * @return array<string, int>|null
     */
    public function getTopCountries(): ?array
    {
        return $this->topCountries;
    }

    /**
     * @param array<string, int>|null $topCountries
     */
    public function setTopCountries(?array $topCountries): static
    {
        $this->topCountries = $topCountries;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
