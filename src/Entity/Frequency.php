<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FrequencyRepository")
 */
class Frequency
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="boolean")
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     */
    private $number;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $category;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $fromStation;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $toStation;

    /**
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="datetime")
     */
    private $crawledAt;

    /**
     * @ORM\Column(type="integer")
     */
    private $status;

    /**
     * @ORM\Column(type="date")
     */
    private $departure;

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
