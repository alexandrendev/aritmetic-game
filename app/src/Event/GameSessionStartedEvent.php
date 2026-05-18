<?php

namespace App\Event;

class GameSessionStartedEvent extends GameSessionEvent
{
    public function __construct(
        \App\Entity\GameSession $session,
        private array $question,
        private int $round,
        private int $totalRounds,
        private int $target,
        private array $participants = []
    ) {
        parent::__construct($session);
    }

    public function getQuestion(): array
    {
        return $this->question;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getTotalRounds(): int
    {
        return $this->totalRounds;
    }

    public function getTarget(): int
    {
        return $this->target;
    }

    public function getParticipants(): array
    {
        return $this->participants;
    }
}
