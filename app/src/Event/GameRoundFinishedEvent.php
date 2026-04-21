<?php

namespace App\Event;

class GameRoundFinishedEvent extends GameSessionEvent
{
    public function __construct(
        \App\Entity\GameSession $session,
        private array $summary
    ) {
        parent::__construct($session);
    }

    public function getSummary(): array
    {
        return $this->summary;
    }
}
