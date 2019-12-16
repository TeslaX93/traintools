<?php

namespace App\Repository;

use App\Entity\FrequencyLastUpdate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
//use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method FrequencyLastUpdate|null find($id, $lockMode = null, $lockVersion = null)
 * @method FrequencyLastUpdate|null findOneBy(array $criteria, array $orderBy = null)
 * @method FrequencyLastUpdate[]    findAll()
 * @method FrequencyLastUpdate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FrequencyLastUpdateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FrequencyLastUpdate::class);
    }

    // /**
    //  * @return FrequencyLastUpdate[] Returns an array of FrequencyLastUpdate objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?FrequencyLastUpdate
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
