<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $externalId;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private Route $route;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $headsign = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shortName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $longName = null;

    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: StopTime::class, orphanRemoval: true)]
    private Collection $stopTimes;

    public function __construct(
        int $externalId,
        Route $route,
        ?string $headsign,
        ?string $shortName,
        ?string $longName,
    ) {
        $this->setExternalId($externalId);
        $this->setRoute($route);
        $this->setHeadsign($headsign);
        $this->setShortName($shortName);
        $this->setLongName($longName);
        $this->stopTimes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): int
    {
        return $this->externalId;
    }

    public function setExternalId(int $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getHeadsign(): ?string
    {
        return $this->headsign;
    }

    public function setHeadsign(?string $headsign): self
    {
        if (empty($headsign) === true) {
            $headsign = null;
        }
        $this->headsign = $headsign;

        return $this;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): self
    {
        if (empty($shortName) === true) {
            $shortName = null;
        }
        $this->shortName = $shortName;

        return $this;
    }

    public function getLongName(): ?string
    {
        return $this->longName;
    }

    public function setLongName(?string $longName): self
    {
        if (empty($longName) === true) {
            $longName = null;
        }
        $this->longName = $longName;

        return $this;
    }

    /**
     * @return Collection<int, StopTime>
     */
    public function getStopTimes(): Collection
    {
        return $this->stopTimes;
    }

    public function addStopTime(StopTime $stopTime): self
    {
        if (!$this->stopTimes->contains($stopTime)) {
            $this->stopTimes->add($stopTime);
            $stopTime->setTrip($this);
        }

        return $this;
    }

    public function removeStopTime(StopTime $stopTime): self
    {
        if ($this->stopTimes->removeElement($stopTime)) {
            // set the owning side to null (unless already changed)
            if ($stopTime->getTrip() === $this) {
                $stopTime->setTrip(null);
            }
        }

        return $this;
    }
}
