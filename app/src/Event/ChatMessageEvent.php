<?php

namespace App\Event;

use App\Entity\BattleRoom;
use App\Entity\Guest;
use Symfony\Contracts\EventDispatcher\Event;

class ChatMessageEvent extends Event
{
    public function __construct(
        private BattleRoom $room,
        private Guest $sender,
        private string $message,
        private string $type = 'message'
    ) {}

    public function getRoom(): BattleRoom
    {
        return $this->room;
    }

    public function getSender(): Guest
    {
        return $this->sender;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
