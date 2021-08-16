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

    /**
    * @return Distance[] Returns an array of Distance objects
    */

    public function getAllStations()
    {

        $conn = $this->getEntityManager()->getConnection();
        $sql = "SELECT station_a FROM distance UNION SELECT station_b FROM distance ORDER BY station_a ASC;";
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function isStationExists($stationName) {
        $stationsList = $this->getAllStations();
        $sl = [];
        foreach($stationsList as $s) {
            $sl[] = $s['station_a'];
        }
        if(in_array($stationName,$sl)) {
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
