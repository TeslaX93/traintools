<?php

namespace App\Entity;

use App\Repository\StationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StationRepository::class)]
#[ORM\Table(name: 'station')]
#[ORM\UniqueConstraint(name: 'uniq_station_url', columns: ['station_url'])]
class Station
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(nullable: true)]
    private ?float $gps_lat = null;

    #[ORM\Column(nullable: true)]
    private ?float $gps_lng = null;

    #[ORM\Column(name: 'display_url', length: 255)]
    private ?string $displayUrl = null;

    #[ORM\Column(name: 'station_url', length: 2048, unique: true)]
    private ?string $stationUrl = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getGpsLat(): ?float
    {
        return $this->gps_lat;
    }

    public function setGpsLat(?float $gps_lat): static
    {
        $this->gps_lat = $gps_lat;

        return $this;
    }

    public function getGpsLng(): ?float
    {
        return $this->gps_lng;
    }

    public function setGpsLng(?float $gps_lng): static
    {
        $this->gps_lng = $gps_lng;

        return $this;
    }

    public function getDisplayUrl(): ?string
    {
        return $this->displayUrl;
    }

    public function setDisplayUrl(string $display_url): static
    {
        $this->displayUrl = $display_url;

        return $this;
    }

    public function getStationUrl(): ?string
    {
        return $this->stationUrl;
    }

    public function setStationUrl(string $station_url): static
    {
        $this->stationUrl = $station_url;

        return $this;
    }
}
