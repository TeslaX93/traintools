<?php

namespace App\Entity;

use App\Repository\DistanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DistanceRepository::class)]
class Distance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $stationA;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $stationB;

    #[ORM\Column(type: Types::FLOAT)]
    private float $distance;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStationA(): ?string
    {
        return $this->stationA;
    }

    public function setStationA(string $stationA): self
    {
        $this->stationA = $stationA;
        return $this;
    }

    public function getStationB(): ?string
    {
        return $this->stationB;
    }

    public function setStationB(string $stationB): self
    {
        $this->stationB = $stationB;
        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(float $distance): self
    {
        $this->distance = $distance;
        return $this;
    }
}
