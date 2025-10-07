<?php

namespace App\Repository;

use App\Entity\Card;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }
 
    public function findByColumnOrdered(\App\Entity\Column $column): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.parentColumn = :c')
            ->setParameter('c', $column)
            ->orderBy('t.position', 'ASC')
            ->getQuery()->getResult();
    }

    public function nextPositionForColumn(\App\Entity\Column $column): int
    {
        $max = $this->createQueryBuilder('card')
            ->select('COALESCE(MAX(card.position), -1)')
            ->andWhere('card.parentColumn = :column')
            ->setParameter('column', $column)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int)$max) + 1;
    }

    //    /**
    //     * @return Card[] Returns an array of Card objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Card
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
