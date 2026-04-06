<?php

namespace App\Event;

use App\Entity\BattleRoom;

class RoundQuestionEvent extends BattleRoomEvent
{
    public function __construct(
        BattleRoom $room,
        private int $round,
        private array $question
    ) {
        parent::__construct($room);
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getQuestion(): array
    {
        return $this->question;
    }
}
