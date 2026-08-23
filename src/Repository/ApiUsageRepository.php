<?php

namespace App\Repository;

use App\Entity\ApiUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiUsage>
 */
class ApiUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiUsage::class);
    }

    /**
     * Najczęściej odpytywane stacje w zadanym okresie.
     *
     * @return list<array{stationId: string, wywolan: int}>
     */
    public function topStations(\DateTimeImmutable $od, int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.stationId AS stationId, COUNT(u.id) AS wywolan')
            ->where('u.requestedAt >= :od')
            ->andWhere('u.stationId IS NOT NULL')
            ->setParameter('od', $od)
            ->groupBy('u.stationId')
            ->orderBy('wywolan', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Liczba wywołań w rozbiciu na endpoint, typ i tryb.
     *
     * @return list<array{endpoint: string, type: string|null, mode: string|null, wywolan: int}>
     */
    public function summary(\DateTimeImmutable $od): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.endpoint AS endpoint, u.type AS type, u.mode AS mode, COUNT(u.id) AS wywolan')
            ->where('u.requestedAt >= :od')
            ->setParameter('od', $od)
            ->groupBy('u.endpoint, u.type, u.mode')
            ->orderBy('wywolan', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
