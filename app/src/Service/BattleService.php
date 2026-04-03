<?php

namespace App\Service;

use App\Entity\BattlePlayer;
use App\Entity\BattleRoom;
use App\Entity\Guest;
use App\Event\BattleFinishedEvent;
use App\Event\BattleStartedEvent;
use App\Event\PlayerAnsweredEvent;
use App\Event\PlayerEliminatedEvent;
use App\Event\RoomPlayerJoinedEvent;
use App\Event\RoomPlayerReadyEvent;
use App\Event\RoundQuestionEvent;
use App\Event\SuddenDeathEvent;
use App\Repository\BattleRoomRepository;
use App\Repository\BattlePlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BattleService
{
    private const BASE_POINTS = 100;
    private const TOOL_PENALTY = 0.5;
    private const TIME_LIMIT_MS = 15000;

    public function __construct(
        private BattleRoomRepository $roomRepository,
        private BattlePlayerRepository $playerRepository,
        private WeaknessAlgorithmService $weaknessAlgorithm,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function createRoom(Guest $creator, ?int $totalRounds = null, ?int $maxPlayers = null): BattleRoom
    {
        $room = new BattleRoom();
        $room->setCreator($creator);

        if ($maxPlayers !== null) {
            $room->setMaxPlayers(
                min(max($maxPlayers, BattleRoom::MIN_PLAYERS), BattleRoom::MAX_PLAYERS)
            );
        }

        if ($totalRounds !== null && in_array($totalRounds, BattleRoom::ALLOWED_ROUNDS)) {
            $room->setTotalRounds($totalRounds);
        }

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        $this->joinRoom($room, $creator);

        return $room;
    }

    public function joinRoom(BattleRoom $room, Guest $guest): BattlePlayer
    {
        if ($room->isRoomFull()) {
            throw new \RuntimeException('A sala está cheia.');
        }

        if ($room->getStatus() !== BattleRoom::STATUS_WAITING) {
            throw new \RuntimeException('A sala já está em andamento.');
        }

        $player = new BattlePlayer();
        $player->setGuest($guest);
        $room->addPlayer($player);

        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new RoomPlayerJoinedEvent($room, $player));

        return $player;
    }

    public function setPlayerReady(BattlePlayer $player): void
    {
        $player->setStatus(BattlePlayer::STATUS_READY);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new RoomPlayerReadyEvent($player->getBattleRoom(), $player));
    }

    public function startBattle(BattleRoom $room): array
    {
        if (!$room->allPlayersReady()) {
            throw new \RuntimeException('Nem todos os jogadores estão prontos.');
        }

        $playerCount = $room->getPlayers()->count();
        $suggestedRounds = BattleRoom::suggestRounds($playerCount);

        if ($room->getTotalRounds() === 10 && $suggestedRounds !== 10) {
            $room->setTotalRounds($suggestedRounds);
        }

        $room->setStatus(BattleRoom::STATUS_IN_PROGRESS);
        $room->setCurrentRound(1);

        foreach ($room->getPlayers() as $player) {
            $player->setStatus(BattlePlayer::STATUS_PLAYING);
        }

        $this->entityManager->flush();

        $question = $this->generateRoundQuestion($room);

        $this->eventDispatcher->dispatch(new BattleStartedEvent($room, $question));

        return $question;
    }

    public function generateRoundQuestion(BattleRoom $room): array
    {
        $alivePlayers = $room->getAlivePlayers();

        $question = $this->weaknessAlgorithm->selectQuestion($alivePlayers);

        return $this->weaknessAlgorithm->generateAnswerOptions($question);
    }

    public function processAnswer(BattlePlayer $player, string $operation, string $answer, int $timeMs, bool $usedTool): array
    {
        $room = $player->getBattleRoom();

        if (!$player->isAlive()) {
            throw new \RuntimeException('Jogador eliminado não pode responder.');
        }

        $isCorrect = false;
        $pointsEarned = 0;
        $wasAlive = $player->isAlive();

        if ($timeMs > self::TIME_LIMIT_MS) {
            $player->loseLife();
        } else {
            $isCorrect = $this->checkAnswer($operation, $answer);

            if ($isCorrect) {
                $pointsEarned = $this->calculatePoints($timeMs, $usedTool);
                $player->addScore($pointsEarned);
            } else {
                $player->loseLife();
            }
        }

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new PlayerAnsweredEvent($room, $player, $isCorrect, $pointsEarned));

        if ($wasAlive && !$player->isAlive()) {
            $this->eventDispatcher->dispatch(new PlayerEliminatedEvent($room, $player));
        }

        return [
            'correct' => $isCorrect,
            'pointsEarned' => $pointsEarned,
            'livesRemaining' => $player->getLives(),
            'status' => $player->getStatus(),
        ];
    }

    public function advanceRound(BattleRoom $room): array
    {
        $alivePlayers = $room->getAlivePlayers();

        if (count($alivePlayers) <= 1) {
            return $this->endBattle($room);
        }

        $nextRound = $room->getCurrentRound() + 1;

        if ($nextRound > $room->getTotalRounds()) {
            return $this->handleEndOfRounds($room);
        }

        $room->setCurrentRound($nextRound);
        $this->entityManager->flush();

        $question = $this->generateRoundQuestion($room);

        $this->eventDispatcher->dispatch(new RoundQuestionEvent($room, $nextRound, $question));

        return [
            'status' => 'next_round',
            'round' => $nextRound,
            'totalRounds' => $room->getTotalRounds(),
            'alivePlayers' => count($alivePlayers),
            'question' => $question,
        ];
    }

    private function handleEndOfRounds(BattleRoom $room): array
    {
        $alivePlayers = $room->getAlivePlayers();

        if (count($alivePlayers) === 1) {
            return $this->endBattle($room);
        }

        $ranked = $this->rankPlayers($alivePlayers);
        $top = $ranked[0];
        $second = $ranked[1];

        if ($this->isTied($top, $second)) {
            $tiedPlayers = $this->getTiedPlayers($ranked);
            $question = $this->generateRoundQuestion($room);

            $tiedData = array_map(fn(BattlePlayer $p) => [
                'id' => $p->getId(),
                'guestId' => $p->getGuest()->getId(),
                'nickname' => $p->getGuest()->getNickName(),
                'lives' => $p->getLives(),
                'score' => $p->getScore(),
            ], $tiedPlayers);

            $this->eventDispatcher->dispatch(new SuddenDeathEvent($room, $tiedData, $question));

            return [
                'status' => 'sudden_death',
                'tiedPlayers' => $tiedData,
                'question' => $question,
            ];
        }

        return $this->endBattle($room);
    }

    public function processSuddenDeath(BattleRoom $room, array $answers): array
    {
        $survivors = [];

        foreach ($answers as $playerId => $data) {
            $player = $this->playerRepository->find($playerId);

            if (!$player || !$player->isAlive()) {
                continue;
            }

            $isCorrect = (bool)($data['correct'] ?? false);
            $timedOut = ($data['timeMs'] ?? self::TIME_LIMIT_MS + 1) > self::TIME_LIMIT_MS;

            if (!$isCorrect || $timedOut) {
                $player->setStatus(BattlePlayer::STATUS_GHOST);
                $this->eventDispatcher->dispatch(new PlayerEliminatedEvent($room, $player));
            } else {
                $survivors[] = $player;
            }
        }

        $this->entityManager->flush();

        if (count($survivors) <= 1) {
            return $this->endBattle($room);
        }

        $question = $this->generateRoundQuestion($room);
        $tiedData = array_map(fn(BattlePlayer $p) => [
            'id' => $p->getId(),
            'guestId' => $p->getGuest()->getId(),
            'nickname' => $p->getGuest()->getNickName(),
        ], $survivors);

        $this->eventDispatcher->dispatch(new SuddenDeathEvent($room, $tiedData, $question));

        return [
            'status' => 'sudden_death',
            'tiedPlayers' => $tiedData,
            'question' => $question,
        ];
    }

    private function endBattle(BattleRoom $room): array
    {
        $room->setStatus(BattleRoom::STATUS_FINISHED);
        $room->setUpdatedAt(new \DateTimeImmutable());

        $allPlayers = $room->getPlayers()->toArray();
        $ranked = $this->rankPlayers($allPlayers);

        $this->entityManager->flush();

        $ranking = array_map(fn(BattlePlayer $p, int $i) => [
            'position' => $i + 1,
            'id' => $p->getId(),
            'guestId' => $p->getGuest()->getId(),
            'nickname' => $p->getGuest()->getNickName(),
            'lives' => $p->getLives(),
            'score' => $p->getScore(),
            'toolsUsed' => $p->getToolsUsedCount(),
        ], $ranked, array_keys($ranked));

        $this->eventDispatcher->dispatch(new BattleFinishedEvent($room, $ranking));

        return [
            'status' => 'finished',
            'ranking' => $ranking,
        ];
    }

    private function rankPlayers(array $players): array
    {
        usort($players, function (BattlePlayer $a, BattlePlayer $b) {
            if ($a->getLives() !== $b->getLives()) {
                return $b->getLives() - $a->getLives();
            }

            if ($a->getToolsUsedCount() !== $b->getToolsUsedCount()) {
                return $a->getToolsUsedCount() - $b->getToolsUsedCount();
            }

            return $b->getScore() - $a->getScore();
        });

        return $players;
    }

    private function isTied(BattlePlayer $a, BattlePlayer $b): bool
    {
        return $a->getLives() === $b->getLives()
            && $a->getToolsUsedCount() === $b->getToolsUsedCount()
            && $a->getScore() === $b->getScore();
    }

    private function getTiedPlayers(array $ranked): array
    {
        $top = $ranked[0];
        $tied = [];

        foreach ($ranked as $player) {
            if ($this->isTied($top, $player)) {
                $tied[] = $player;
            }
        }

        return $tied;
    }

    private function checkAnswer(string $operation, string $answer): bool
    {
        $parts = explode('x', $operation);

        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($answer)) {
            return false;
        }

        return (int)$parts[0] * (int)$parts[1] === (int)$answer;
    }

    private function calculatePoints(int $timeMs, bool $usedTool): int
    {
        $timeBonus = max(0, (self::TIME_LIMIT_MS - $timeMs) / self::TIME_LIMIT_MS);
        $points = (int)(self::BASE_POINTS * (0.5 + 0.5 * $timeBonus));

        if ($usedTool) {
            $points = (int)($points * self::TOOL_PENALTY);
        }

        return $points;
    }
}
