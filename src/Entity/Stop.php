<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class Stop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $externalId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private float $latitude;

    #[ORM\Column]
    private float $longitude;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $parent = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $platform = null;

    #[ORM\OneToMany(mappedBy: 'stop', targetEntity: StopTime::class)]
    private Collection $stopTimes;

    #[ORM\ManyToOne(inversedBy: 'stops')]
    private ?Node $node = null;

    public function __construct(
        string $externalId,
        string $name,
        float $latitude,
        float $longitude,
        ?string $parent,
        ?string $platform,
    ) {
        $this->setExternalId($externalId);
        $this->setName($name);
        $this->setLatitude($latitude);
        $this->setLongitude($longitude);
        $this->setParent($parent);
        $this->setPlatform($platform);
        $this->stopTimes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getParent(): ?string
    {
        return $this->parent;
    }

    public function setParent(?string $parent): self
    {
        if (empty($parent) === true) {
            $parent = null;
        }

        $this->parent = $parent;

        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): self
    {
        if (empty($platform) === true) {
            $platform = null;
        }

        $this->platform = $platform;

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
            $stopTime->setStop($this);
        }

        return $this;
    }

    public function removeStopTime(StopTime $stopTime): self
    {
        if ($this->stopTimes->removeElement($stopTime)) {
            // set the owning side to null (unless already changed)
            if ($stopTime->getStop() === $this) {
                $stopTime->setStop(null);
            }
        }

        return $this;
    }

    public function getNode(): ?Node
    {
        return $this->node;
    }

    public function setNode(?Node $node): self
    {
        $this->node = $node;

        return $this;
    }
}
