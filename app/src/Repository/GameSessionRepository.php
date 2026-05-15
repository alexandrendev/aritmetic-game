<?php

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSession>
 */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    /**
     * @return GameSession[]
     */
    public function findActiveByUserId(int $userId): array
    {
        return $this->createQueryBuilder('gs')
            ->where('gs.userId = :userId')
            ->andWhere('gs.status IN (:statuses)')
            ->setParameter('userId', $userId)
            ->setParameter('statuses', [Status::WAITING, Status::PLAYING])
            ->orderBy('gs.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
