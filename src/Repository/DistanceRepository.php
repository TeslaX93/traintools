<?php

namespace App\Repository;

use App\Entity\Distance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Distance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Distance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Distance[]    findAll()
 * @method Distance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DistanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Distance::class);
    }

    public function getAllStations(): array
    {

        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT station_a FROM distance UNION SELECT station_b FROM distance ORDER BY station_a ASC;";
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery();

        return array_keys($stmt->fetchAllAssociativeIndexed());
    }

    public function isStationExists($stationName): bool
    {
        $stationsList = $this->getAllStations();

        if(in_array($stationName,$stationsList)) {
            return true;
        }
        return false;

    }


    /*
    public function findOneBySomeField($value): ?Distance
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
