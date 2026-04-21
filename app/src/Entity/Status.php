<?php

namespace App\Entity;

enum Status: string
{
    case WAITING = 'waiting';
    case READY = 'ready';
    case PLAYING = 'playing';
    case FINISHED = 'finished';
}
