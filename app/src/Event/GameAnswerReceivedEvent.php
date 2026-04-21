<?php

namespace App\Event;

use App\Entity\GameSession;
use App\Entity\GameSessionGuest;

class GameAnswerReceivedEvent extends GameSessionEvent
{
    public function __construct(
        GameSession $session,
        private GameSessionGuest $participant,
        private bool $correct,
        private int $pointsEarned,
        private int $submittedAnswer,
        private int $timeMs
    ) {
        parent::__construct($session);
    }

    public function getParticipant(): GameSessionGuest
    {
        return $this->participant;
    }

    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function getPointsEarned(): int
    {
        return $this->pointsEarned;
    }

    public function getSubmittedAnswer(): int
    {
        return $this->submittedAnswer;
    }

    public function getTimeMs(): int
    {
        return $this->timeMs;
    }
}
