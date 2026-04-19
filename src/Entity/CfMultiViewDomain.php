<?php

namespace Michallkanak\SymfonyCloudflareMultiView\Entity;

use Doctrine\ORM\Mapping as ORM;
use Michallkanak\SymfonyCloudflareMultiView\Repository\CfMultiViewDomainRepository;

#[ORM\Entity(repositoryClass: CfMultiViewDomainRepository::class)]
#[ORM\Table(name: 'cf_multi_view_domain')]
class CfMultiViewDomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $zoneId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * Account name from configuration. Acts as the group on the dashboard.
     */
    #[ORM\Column(length: 255)]
    private string $accountName = '';

    #[ORM\Column]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZoneId(): ?string
    {
        return $this->zoneId;
    }

    public function setZoneId(string $zoneId): static
    {
        $this->zoneId = $zoneId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function setAccountName(string $accountName): static
    {
        $this->accountName = $accountName;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
