<?php

namespace App\Event;

use App\Entity\BattleRoom;

class BattleStartedEvent extends BattleRoomEvent
{
    public function __construct(
        BattleRoom $room,
        private array $question
    ) {
        parent::__construct($room);
    }

    public function getQuestion(): array
    {
        return $this->question;
    }
}
