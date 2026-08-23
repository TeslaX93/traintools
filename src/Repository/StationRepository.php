<?php

namespace App\Repository;

use App\Entity\Station;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Station>
 */
class StationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Station::class);
    }

    /**
     * @return list<string>
     */
    public function findAllNames(): array
    {
        return array_column(
            $this->createQueryBuilder('s')->select('s.name')->getQuery()->getArrayResult(),
            'name'
        );
    }

    /**
     * Stacje z ustalonym polozeniem, na potrzeby mapy.
     *
     * @return list<array{name: string, address: string|null, lat: float, lng: float, displayUrl: string|null}>
     */
    public function findForMap(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.name, s.address, s.gps_lat AS lat, s.gps_lng AS lng, s.displayUrl')
            ->where('s.gps_lat IS NOT NULL')
            ->andWhere('s.gps_lng IS NOT NULL')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findForPanels(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id, s.name, s.displayUrl')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

//    /**
//     * @return Station[] Returns an array of Station objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Station
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
