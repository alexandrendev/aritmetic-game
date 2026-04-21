<?php

namespace App\Event;

class GameQuestionGeneratedEvent extends GameSessionEvent
{
    public function __construct(
        \App\Entity\GameSession $session,
        private array $question
    ) {
        parent::__construct($session);
    }

    public function getQuestion(): array
    {
        return $this->question;
    }
}
