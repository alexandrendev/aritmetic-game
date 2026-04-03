<?php

namespace App\Event;

use App\Entity\BattleRoom;
use Symfony\Contracts\EventDispatcher\Event;

abstract class BattleRoomEvent extends Event
{
    public function __construct(
        private BattleRoom $room
    ) {}

    public function getRoom(): BattleRoom
    {
        return $this->room;
    }
}
