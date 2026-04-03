<?php

namespace App\Event;

use App\Entity\BattlePlayer;
use App\Entity\BattleRoom;

class PlayerAnsweredEvent extends BattleRoomEvent
{
    public function __construct(
        BattleRoom $room,
        private BattlePlayer $player,
        private bool $correct,
        private int $pointsEarned
    ) {
        parent::__construct($room);
    }

    public function getPlayer(): BattlePlayer
    {
        return $this->player;
    }

    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function getPointsEarned(): int
    {
        return $this->pointsEarned;
    }
}
