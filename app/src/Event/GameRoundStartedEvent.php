<?php

namespace App\Event;

class GameRoundStartedEvent extends GameSessionEvent
{
    public function __construct(
        \App\Entity\GameSession $session,
        private int $round,
        private array $question
    ) {
        parent::__construct($session);
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getQuestion(): array
    {
        return $this->question;
    }
}
