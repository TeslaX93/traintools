<?php

namespace App\Entity;

use App\Repository\FrequencyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FrequencyRepository::class)]
class Frequency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $type;

    #[ORM\Column(type: Types::INTEGER)]
    private int $number;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $category;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $fromStation;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $toStation;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $crawledAt;

    #[ORM\Column(type: Types::INTEGER)]
    private int $status;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $departure;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?bool
    {
        return $this->type;
    }

    public function setType(bool $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getFromStation(): ?string
    {
        return $this->fromStation;
    }

    public function setFromStation(string $fromStation): self
    {
        $this->fromStation = $fromStation;
        return $this;
    }

    public function getToStation(): ?string
    {
        return $this->toStation;
    }

    public function setToStation(string $toStation): self
    {
        $this->toStation = $toStation;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCrawledAt(): ?\DateTimeInterface
    {
        return $this->crawledAt;
    }

    public function setCrawledAt(\DateTimeInterface $crawledAt): self
    {
        $this->crawledAt = $crawledAt;
        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getDeparture(): ?\DateTimeInterface
    {
        return $this->departure;
    }

    public function setDeparture(\DateTimeInterface $departure): self
    {
        $this->departure = $departure;
        return $this;
    }
}
