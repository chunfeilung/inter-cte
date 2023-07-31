<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
class StopTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stopTimes')]
    #[ORM\JoinColumn(nullable: false)]
    private Trip $trip;

    #[ORM\Column]
    private int $stopSequence;

    #[ORM\ManyToOne(inversedBy: 'stopTimes')]
    private ?Stop $stop = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stopHeadsign = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $arrivalTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $departureTime = null;

    #[ORM\Column]
    private float $shapeDistTraveled;

    public function __construct(
        Trip $trip,
        int $stopSequence,
        Stop $stop,
        ?string $stopHeadsign,
        \DateTimeInterface $arrivalTime,
        \DateTimeInterface $departureTime,
        float $shapeDistTraveled,
    ) {
        $this->setTrip($trip);
        $this->setStopSequence($stopSequence);
        $this->setStop($stop);
        $this->setStopHeadsign($stopHeadsign);
        $this->setArrivalTime($arrivalTime);
        $this->setDepartureTime($departureTime);
        $this->setShapeDistTraveled($shapeDistTraveled);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): self
    {
        $this->trip = $trip;

        return $this;
    }

    public function getStopSequence(): ?int
    {
        return $this->stopSequence;
    }

    public function setStopSequence(int $stopSequence): self
    {
        $this->stopSequence = $stopSequence;

        return $this;
    }

    public function getStop(): ?Stop
    {
        return $this->stop;
    }

    public function setStop(?Stop $stop): self
    {
        $this->stop = $stop;

        return $this;
    }

    public function getStopHeadsign(): ?string
    {
        return $this->stopHeadsign;
    }

    public function setStopHeadsign(?string $stopHeadsign): self
    {
        if (empty($stopHeadsign) === true) {
            $stopHeadsign = null;
        }
        $this->stopHeadsign = $stopHeadsign;

        return $this;
    }

    public function getArrivalTime(): ?\DateTimeInterface
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(?\DateTimeInterface $arrivalTime): self
    {
        $this->arrivalTime = $arrivalTime;

        return $this;
    }

    public function getDepartureTime(): ?\DateTimeInterface
    {
        return $this->departureTime;
    }

    public function setDepartureTime(?\DateTimeInterface $departureTime): self
    {
        $this->departureTime = $departureTime;

        return $this;
    }

    public function getShapeDistTraveled(): float
    {
        return $this->shapeDistTraveled;
    }

    public function setShapeDistTraveled(float $shapeDistTraveled): self
    {
        $this->shapeDistTraveled = $shapeDistTraveled;

        return $this;
    }
}
