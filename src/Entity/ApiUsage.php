<?php

namespace App\Entity;

use App\Repository\ApiUsageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pojedyncze wywołanie publicznego API.
 *
 * Celowo nie zapisujemy adresu IP ani nagłówka User-Agent — do statystyk
 * użycia nie są potrzebne, a byłyby danymi osobowymi.
 */
#[ORM\Entity(repositoryClass: ApiUsageRepository::class)]
#[ORM\Table(name: 'api_usage')]
#[ORM\Index(name: 'idx_api_usage_requested_at', columns: ['requested_at'])]
#[ORM\Index(name: 'idx_api_usage_endpoint', columns: ['endpoint'])]
class ApiUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'requested_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $requestedAt;

    /** Nazwa trasy, np. app_bilkom_api. */
    #[ORM\Column(length: 64)]
    private string $endpoint;

    /** departures, arrivals, nextdeparture, nextarrival - tylko dla tablic. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $type;

    /** basic, extended - tylko dla tablic. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $mode;

    /** Numer stacji wg bilkom.pl - tylko dla tablic. */
    #[ORM\Column(name: 'station_id', length: 32, nullable: true)]
    private ?string $stationId;

    public function __construct(
        string $endpoint,
        ?string $type = null,
        ?string $mode = null,
        ?string $stationId = null,
        ?\DateTimeImmutable $requestedAt = null,
    ) {
        $this->endpoint = $endpoint;
        $this->type = $type;
        $this->mode = $mode;
        $this->stationId = $stationId;
        $this->requestedAt = $requestedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function getStationId(): ?string
    {
        return $this->stationId;
    }
}
