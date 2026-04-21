<?php

namespace App\Message;

class CloseGameRoundMessage
{
    public function __construct(
        private int $sessionId,
        private int $round,
        private string $questionId
    ) {
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getQuestionId(): string
    {
        return $this->questionId;
    }
}
