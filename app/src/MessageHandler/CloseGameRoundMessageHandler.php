<?php

namespace App\MessageHandler;

use App\Message\CloseGameRoundMessage;
use App\Repository\GameSessionRepository;
use App\Service\GameSessionEngineService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CloseGameRoundMessageHandler
{
    public function __construct(
        private GameSessionRepository $gameSessionRepository,
        private GameSessionEngineService $gameSessionEngineService
    ) {
    }

    public function __invoke(CloseGameRoundMessage $message): void
    {
        $session = $this->gameSessionRepository->find($message->getSessionId());
        if (!$session) {
            return;
        }

        $this->gameSessionEngineService->closeRoundByTimeout(
            $session,
            $message->getRound(),
            $message->getQuestionId()
        );
    }
}
