<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Index(fields: ['fromNode', 'stops'], name: 'stops_idx')]
#[ORM\Index(fields: ['fromNode', 'distance'], name: 'distance_idx')]
class Edge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Node $fromNode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Node $toNode = null;

    #[ORM\Column]
    private ?int $distance = null;

    #[ORM\Column]
    private ?int $stops = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromNode(): ?Node
    {
        return $this->fromNode;
    }

    public function setFromNode(?Node $fromNode): self
    {
        $this->fromNode = $fromNode;

        return $this;
    }

    public function getToNode(): ?Node
    {
        return $this->toNode;
    }

    public function setToNode(?Node $toNode): self
    {
        $this->toNode = $toNode;

        return $this;
    }

    public function getDistance(): ?int
    {
        return $this->distance;
    }

    public function setDistance(int $distance): self
    {
        $this->distance = $distance;

        return $this;
    }

    public function getStops(): ?int
    {
        return $this->stops;
    }

    public function setStops(int $stops): self
    {
        $this->stops = $stops;

        return $this;
    }
}
