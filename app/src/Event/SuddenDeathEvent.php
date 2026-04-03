<?php

namespace App\Event;

use App\Entity\BattleRoom;

class SuddenDeathEvent extends BattleRoomEvent
{
    public function __construct(
        BattleRoom $room,
        private array $tiedPlayers,
        private array $question
    ) {
        parent::__construct($room);
    }

    public function getTiedPlayers(): array
    {
        return $this->tiedPlayers;
    }

    public function getQuestion(): array
    {
        return $this->question;
    }
}
