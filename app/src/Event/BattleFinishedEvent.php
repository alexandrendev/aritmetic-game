<?php

namespace App\Event;

use App\Entity\BattleRoom;

class BattleFinishedEvent extends BattleRoomEvent
{
    public function __construct(
        BattleRoom $room,
        private array $ranking
    ) {
        parent::__construct($room);
    }

    public function getRanking(): array
    {
        return $this->ranking;
    }
}
