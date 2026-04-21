<?php

namespace App\Event;

class GameSessionFinishedEvent extends GameSessionEvent
{
    public function __construct(
        \App\Entity\GameSession $session,
        private array $ranking,
        private string $reason
    ) {
        parent::__construct($session);
    }

    public function getRanking(): array
    {
        return $this->ranking;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
