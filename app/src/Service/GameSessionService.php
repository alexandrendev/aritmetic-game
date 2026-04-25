<?php

namespace App\Service;

use App\Repository\GameSessionRepository;

class GameSessionService
{
    private const ROOM_CODE_LENGTH = 10;

    public function __construct(
        private GameSessionRepository $gameSessionRepository,
    )
    {
    }

    public function generateRoomsCode()
    {
        $bytes = random_bytes(self::ROOM_CODE_LENGTH);

        return substr(bin2hex($bytes), 0, self::ROOM_CODE_LENGTH);
    }
}
