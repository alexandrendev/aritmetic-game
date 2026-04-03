<?php

namespace App\Event;

use App\Entity\BattlePlayer;
use App\Entity\BattleRoom;

class RoomPlayerJoinedEvent extends BattleRoomEvent
{
    public function __construct(
        BattleRoom $room,
        private BattlePlayer $player
    ) {
        parent::__construct($room);
    }

    public function getPlayer(): BattlePlayer
    {
        return $this->player;
    }
}
