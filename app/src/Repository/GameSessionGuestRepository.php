<?php

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\GameSessionGuest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSessionGuest>
 */
class GameSessionGuestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSessionGuest::class);
    }

    /**
     * @return GameSessionGuest[]
     */
    public function findBySession(GameSession $session): array
    {
        return $this->findBy(['gameSession' => $session], ['id' => 'ASC']);
    }

    /**
     * @return GameSessionGuest[]
     */
    public function findAliveBySession(GameSession $session): array
    {
        return $this->findBy(
            ['gameSession' => $session, 'isAlive' => true],
            ['id' => 'ASC']
        );
    }

    public function findOneBySessionAndId(GameSession $session, int $id): ?GameSessionGuest
    {
        return $this->findOneBy(['gameSession' => $session, 'id' => $id]);
    }
}
