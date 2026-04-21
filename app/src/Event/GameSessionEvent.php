<?php

namespace App\Event;

use App\Entity\GameSession;
use Symfony\Contracts\EventDispatcher\Event;

abstract class GameSessionEvent extends Event
{
    public function __construct(
        private GameSession $session
    ) {
    }

    public function getSession(): GameSession
    {
        return $this->session;
    }
}
