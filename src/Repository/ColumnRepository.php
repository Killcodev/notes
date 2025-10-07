<?php

namespace App\Repository;

use App\Entity\Column;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Column>
 */
class ColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Column::class);
    }

    public function findByBoardOrdered(\App\Entity\Board $board): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.board = :b')->setParameter('b', $board)
            ->orderBy('c.position', 'ASC')
            ->getQuery()->getResult();
    }

    public function nextPositionForBoard(\App\Entity\Board $board): int
    {
        $max = $this->createQueryBuilder('c')
            ->select('COALESCE(MAX(c.position), -1)')
            ->andWhere('c.board = :board')
            ->setParameter('board', $board)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int)$max) + 1;
    }

//    /**
//     * @return Column[] Returns an array of Column objects
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

//    public function findOneBySomeField($value): ?Column
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
