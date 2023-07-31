<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Transfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stop $fromStop = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Stop $toStop = null;

    #[ORM\Column]
    private ?int $minTransferTime = null;

    public function __construct(
        Stop $fromStop,
        Stop $toStop,
        int $minTransferTime
    ) {
        $this->setFromStop($fromStop);
        $this->setToStop($toStop);
        $this->setMinTransferTime($minTransferTime);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromStop(): ?Stop
    {
        return $this->fromStop;
    }

    public function setFromStop(?Stop $fromStop): self
    {
        $this->fromStop = $fromStop;

        return $this;
    }

    public function getToStop(): ?Stop
    {
        return $this->toStop;
    }

    public function setToStop(?Stop $toStop): self
    {
        $this->toStop = $toStop;

        return $this;
    }

    public function getMinTransferTime(): ?int
    {
        return $this->minTransferTime;
    }

    public function setMinTransferTime(int $minTransferTime): self
    {
        $this->minTransferTime = $minTransferTime;

        return $this;
    }
}
