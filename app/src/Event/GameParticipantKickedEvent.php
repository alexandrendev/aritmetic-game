<?php

namespace App\Event;

use App\Entity\GameSession;
use App\Entity\GameSessionGuest;

class GameParticipantKickedEvent extends GameSessionEvent
{
    public function __construct(
        GameSession $session,
        private GameSessionGuest $participant
    ) {
        parent::__construct($session);
    }

    public function getParticipant(): GameSessionGuest
    {
        return $this->participant;
    }
}
