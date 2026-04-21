<?php

namespace App\Service;

use App\Entity\GameSession;
use App\Entity\GameSessionGuest;
use App\Entity\Status;
use App\Event\GameAnswerReceivedEvent;
use App\Event\GameParticipantEliminatedEvent;
use App\Event\GameParticipantUpdatedEvent;
use App\Event\GameQuestionGeneratedEvent;
use App\Event\GameRoundFinishedEvent;
use App\Event\GameRoundStartedEvent;
use App\Event\GameSessionFinishedEvent;
use App\Event\GameSessionStartedEvent;
use App\Message\CloseGameRoundMessage;
use App\Repository\GameSessionGuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class GameSessionEngineService
{
    private const DEFAULT_TOTAL_ROUNDS = 10;
    private const MAX_TOTAL_ROUNDS = 100;
    private const DEFAULT_RESPONSE_WINDOW_MS = 15000;
    private const MIN_RESPONSE_WINDOW_MS = 3000;
    private const MAX_RESPONSE_WINDOW_MS = 120000;
    private const BASE_POINTS = 100;

    public function __construct(
        private GameSessionGuestRepository $gameSessionGuestRepository,
        private GameQuestionGeneratorService $questionGenerator,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function startSession(
        GameSession $session,
        ?int $target = null,
        ?int $totalRounds = null,
        ?int $responseWindowMs = null
    ): array {
        if ($session->getStatus() === Status::PLAYING) {
            throw new \RuntimeException('Game session is already in progress.');
        }

        $participants = $this->gameSessionGuestRepository->findBySession($session);
        if (count($participants) === 0) {
            throw new \RuntimeException('Cannot start a game session without participants.');
        }

        $resolvedTarget = $this->questionGenerator->resolveTarget($target);
        $resolvedDifficulty = $this->questionGenerator->resolveDifficultyByTarget($resolvedTarget);
        $resolvedTotalRounds = $this->resolveTotalRounds($totalRounds);
        $resolvedResponseWindowMs = $this->resolveResponseWindowMs($responseWindowMs);
        $startedAt = new \DateTimeImmutable();

        $question = $this->questionGenerator->generateQuestion($resolvedTarget, 1, $resolvedResponseWindowMs);

        $session->setStatus(Status::PLAYING);
        $session->setDifficulty($resolvedDifficulty);
        $session->setState([
            'target' => $resolvedTarget,
            'round' => 1,
            'totalRounds' => $resolvedTotalRounds,
            'responseWindowMs' => $resolvedResponseWindowMs,
            'question' => $question,
            'answers' => [],
            'roundStartedAt' => $startedAt->format(DATE_ATOM),
            'startedAt' => $startedAt->format(DATE_ATOM),
        ]);

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new GameSessionStartedEvent(
            $session,
            $question,
            1,
            $resolvedTotalRounds,
            $resolvedTarget
        ));
        $this->eventDispatcher->dispatch(new GameRoundStartedEvent($session, 1, $question));
        $this->eventDispatcher->dispatch(new GameQuestionGeneratedEvent($session, $question));

        $this->scheduleRoundClose($session, 1, (string) $question['id'], $resolvedResponseWindowMs);

        return [
            'status' => 'started',
            'round' => 1,
            'totalRounds' => $resolvedTotalRounds,
            'question' => $question,
            'session' => $this->serializeSession($session),
        ];
    }

    public function answerCurrentQuestion(GameSession $session, GameSessionGuest $participant, int $answer, int $timeMs): array
    {
        if ($session->getStatus() !== Status::PLAYING) {
            throw new \RuntimeException('Game session is not in progress.');
        }

        if ($participant->getGameSession()?->getId() !== $session->getId()) {
            throw new \RuntimeException('Participant does not belong to this game session.');
        }

        if (!$participant->isAlive()) {
            throw new \RuntimeException('Eliminated participant cannot answer.');
        }

        $state = $this->getSessionState($session);
        $question = $state['question'] ?? null;

        if (!is_array($question) || !array_key_exists('correctAnswer', $question)) {
            throw new \RuntimeException('No active question for this game session.');
        }

        if (isset($state['roundClosedAt'])) {
            throw new \RuntimeException('Round already closed.');
        }

        $participantId = $participant->getId();
        if (null === $participantId) {
            throw new \RuntimeException('Participant must be persisted before answering.');
        }

        $participantKey = (string) $participantId;
        $answers = is_array($state['answers'] ?? null) ? $state['answers'] : [];
        if (array_key_exists($participantKey, $answers)) {
            throw new \RuntimeException('Participant already answered this round.');
        }

        $windowMs = $this->getResponseWindowMs($state);
        $submittedAt = new \DateTimeImmutable();
        $roundExpired = $this->isRoundExpired($state, $submittedAt);
        $normalizedTimeMs = max(0, $timeMs);
        $timedOut = $roundExpired || $normalizedTimeMs > $windowMs;
        $correct = !$timedOut && $answer === (int) $question['correctAnswer'];

        $pointsEarned = 0;
        $eliminated = false;

        if ($correct) {
            $pointsEarned = $this->calculatePoints($normalizedTimeMs, $windowMs);
            $participant->setScore($participant->getScore() + $pointsEarned);
        } else {
            $eliminated = $this->applyPenalty($participant);
        }

        $answers[$participantKey] = [
            'answer' => $answer,
            'correct' => $correct,
            'pointsEarned' => $pointsEarned,
            'timeMs' => $normalizedTimeMs,
            'timedOut' => $timedOut,
            'lateAnswer' => $timedOut,
            'answeredAt' => $submittedAt->format(DATE_ATOM),
        ];
        $state['answers'] = $answers;
        $session->setState($state);

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new GameAnswerReceivedEvent(
            $session,
            $participant,
            $correct,
            $pointsEarned,
            $answer,
            $normalizedTimeMs
        ));
        $this->eventDispatcher->dispatch(new GameParticipantUpdatedEvent($session, $participant));

        if ($eliminated) {
            $this->eventDispatcher->dispatch(new GameParticipantEliminatedEvent($session, $participant));
        }

        $result = [
            'correct' => $correct,
            'pointsEarned' => $pointsEarned,
            'timedOut' => $timedOut,
            'livesRemaining' => $participant->getLives(),
            'isAlive' => $participant->isAlive(),
            'roundFinished' => false,
        ];

        $aliveParticipants = $this->gameSessionGuestRepository->findAliveBySession($session);
        $allAliveAnswered = $this->haveAllAliveAnswered($aliveParticipants, $answers);

        if ($timedOut || $allAliveAnswered) {
            $closeReason = $timedOut ? 'timeout' : 'all_answered';
            $transition = $this->closeCurrentRoundAndTransition($session, $closeReason);
            $result['roundFinished'] = true;
            $result['next'] = $transition;
        }

        return $result;
    }

    /**
     * Called by delayed Messenger message to close a round when response window expires.
     */
    public function closeRoundByTimeout(GameSession $session, int $expectedRound, string $expectedQuestionId): ?array
    {
        if ($session->getStatus() !== Status::PLAYING) {
            return null;
        }

        $state = $this->getSessionState($session);
        $activeRound = isset($state['round']) ? (int) $state['round'] : 0;
        $questionId = is_array($state['question'] ?? null) ? (string) ($state['question']['id'] ?? '') : '';

        if ($activeRound !== $expectedRound || $questionId !== $expectedQuestionId || isset($state['roundClosedAt'])) {
            return null;
        }

        return $this->closeCurrentRoundAndTransition($session, 'timeout');
    }

    public function finishSession(GameSession $session, string $reason = 'manual'): array
    {
        $participants = $this->gameSessionGuestRepository->findBySession($session);
        $ranking = $this->buildRanking($participants);

        $state = $this->getSessionState($session);
        $state['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $state['finishReason'] = $reason;
        $state['ranking'] = $ranking;
        $session->setState($state);
        $session->setStatus(Status::FINISHED);

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new GameSessionFinishedEvent($session, $ranking, $reason));

        return [
            'status' => 'finished',
            'reason' => $reason,
            'ranking' => $ranking,
            'session' => $this->serializeSession($session),
        ];
    }

    private function closeCurrentRoundAndTransition(GameSession $session, string $closeReason): array
    {
        $state = $this->getSessionState($session);
        if (isset($state['roundClosedAt'])) {
            return ['status' => 'round_already_closed'];
        }

        $participants = $this->gameSessionGuestRepository->findBySession($session);
        $answers = is_array($state['answers'] ?? null) ? $state['answers'] : [];
        $closedAt = new \DateTimeImmutable();
        $participantEvents = [];

        foreach ($participants as $participant) {
            $participantId = $participant->getId();
            if (null === $participantId || !$participant->isAlive()) {
                continue;
            }

            $participantKey = (string) $participantId;
            if (array_key_exists($participantKey, $answers)) {
                continue;
            }

            $eliminated = $this->applyPenalty($participant);
            $answers[$participantKey] = [
                'answer' => null,
                'correct' => false,
                'pointsEarned' => 0,
                'timeMs' => null,
                'timedOut' => true,
                'noResponse' => true,
                'answeredAt' => $closedAt->format(DATE_ATOM),
            ];

            $participantEvents[] = [
                'participant' => $participant,
                'eliminated' => $eliminated,
            ];
        }

        $state['answers'] = $answers;
        $state['roundClosedAt'] = $closedAt->format(DATE_ATOM);
        $state['roundCloseReason'] = $closeReason;
        $session->setState($state);

        $this->entityManager->flush();

        foreach ($participantEvents as $eventData) {
            /** @var GameSessionGuest $eventParticipant */
            $eventParticipant = $eventData['participant'];
            $this->eventDispatcher->dispatch(new GameParticipantUpdatedEvent($session, $eventParticipant));

            if ($eventData['eliminated']) {
                $this->eventDispatcher->dispatch(new GameParticipantEliminatedEvent($session, $eventParticipant));
            }
        }

        $latestState = $this->getSessionState($session);
        $summary = $this->buildRoundSummary($session, $latestState);
        $this->eventDispatcher->dispatch(new GameRoundFinishedEvent($session, $summary));

        $aliveParticipants = $this->gameSessionGuestRepository->findAliveBySession($session);

        if ($this->shouldFinishSession($session, $aliveParticipants)) {
            $reason = count($aliveParticipants) <= 1 ? 'last_alive' : 'total_rounds';

            return $this->finishSession($session, $reason);
        }

        return $this->startNextRound($session);
    }

    private function startNextRound(GameSession $session): array
    {
        $state = $this->getSessionState($session);
        $target = isset($state['target']) ? (int) $state['target'] : 0;
        $currentRound = isset($state['round']) ? (int) $state['round'] : 0;
        $responseWindowMs = $this->getResponseWindowMs($state);

        if ($target < GameQuestionGeneratorService::MIN_TARGET || $target > GameQuestionGeneratorService::MAX_TARGET) {
            throw new \RuntimeException('Invalid target in game state.');
        }

        $nextRound = $currentRound + 1;
        $question = $this->questionGenerator->generateQuestion($target, $nextRound, $responseWindowMs);
        $roundStartedAt = new \DateTimeImmutable();

        $state['round'] = $nextRound;
        $state['question'] = $question;
        $state['answers'] = [];
        $state['roundStartedAt'] = $roundStartedAt->format(DATE_ATOM);
        unset($state['roundClosedAt'], $state['roundCloseReason']);

        $session->setState($state);

        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new GameRoundStartedEvent($session, $nextRound, $question));
        $this->eventDispatcher->dispatch(new GameQuestionGeneratedEvent($session, $question));

        $this->scheduleRoundClose($session, $nextRound, (string) $question['id'], $responseWindowMs);

        return [
            'status' => 'round_started',
            'round' => $nextRound,
            'totalRounds' => $this->getTotalRounds($session),
            'question' => $question,
            'session' => $this->serializeSession($session),
        ];
    }

    private function scheduleRoundClose(GameSession $session, int $round, string $questionId, int $responseWindowMs): void
    {
        $sessionId = $session->getId();
        if (null === $sessionId) {
            throw new \RuntimeException('Cannot schedule round close for non-persisted session.');
        }

        $delayMs = max(0, $responseWindowMs + 100);
        $this->messageBus->dispatch(
            new CloseGameRoundMessage($sessionId, $round, $questionId),
            [new DelayStamp($delayMs)]
        );
    }

    private function resolveTotalRounds(?int $totalRounds): int
    {
        if (null === $totalRounds) {
            return self::DEFAULT_TOTAL_ROUNDS;
        }

        if ($totalRounds < 1 || $totalRounds > self::MAX_TOTAL_ROUNDS) {
            throw new \InvalidArgumentException(
                sprintf('totalRounds must be between 1 and %d.', self::MAX_TOTAL_ROUNDS)
            );
        }

        return $totalRounds;
    }

    private function resolveResponseWindowMs(?int $responseWindowMs): int
    {
        if (null === $responseWindowMs) {
            return self::DEFAULT_RESPONSE_WINDOW_MS;
        }

        if ($responseWindowMs < self::MIN_RESPONSE_WINDOW_MS || $responseWindowMs > self::MAX_RESPONSE_WINDOW_MS) {
            throw new \InvalidArgumentException(
                sprintf(
                    'responseWindowMs must be between %d and %d.',
                    self::MIN_RESPONSE_WINDOW_MS,
                    self::MAX_RESPONSE_WINDOW_MS
                )
            );
        }

        return $responseWindowMs;
    }

    private function getSessionState(GameSession $session): array
    {
        $state = $session->getState();

        return is_array($state) ? $state : [];
    }

    private function shouldFinishSession(GameSession $session, array $aliveParticipants): bool
    {
        if (count($aliveParticipants) <= 1) {
            return true;
        }

        return $this->getCurrentRound($session) >= $this->getTotalRounds($session);
    }

    private function getCurrentRound(GameSession $session): int
    {
        $state = $this->getSessionState($session);

        return isset($state['round']) ? (int) $state['round'] : 1;
    }

    private function getTotalRounds(GameSession $session): int
    {
        $state = $this->getSessionState($session);

        return isset($state['totalRounds']) ? (int) $state['totalRounds'] : self::DEFAULT_TOTAL_ROUNDS;
    }

    private function getResponseWindowMs(array $state): int
    {
        return isset($state['responseWindowMs'])
            ? (int) $state['responseWindowMs']
            : self::DEFAULT_RESPONSE_WINDOW_MS;
    }

    private function isRoundExpired(array $state, \DateTimeImmutable $currentTime): bool
    {
        $roundStartedAtRaw = $state['roundStartedAt'] ?? null;
        if (!is_string($roundStartedAtRaw) || trim($roundStartedAtRaw) === '') {
            return false;
        }

        try {
            $roundStartedAt = new \DateTimeImmutable($roundStartedAtRaw);
        } catch (\Exception) {
            return false;
        }

        $responseWindowMs = $this->getResponseWindowMs($state);
        $deadline = $roundStartedAt->modify(sprintf('+%d milliseconds', $responseWindowMs));

        return $currentTime > $deadline;
    }

    private function haveAllAliveAnswered(array $aliveParticipants, array $answers): bool
    {
        foreach ($aliveParticipants as $participant) {
            $id = $participant->getId();
            if (null === $id || !array_key_exists((string) $id, $answers)) {
                return false;
            }
        }

        return true;
    }

    private function applyPenalty(GameSessionGuest $participant): bool
    {
        $nextLives = max(0, $participant->getLives() - 1);
        $participant->setLives($nextLives);

        if ($nextLives !== 0) {
            return false;
        }

        $participant->setIsAlive(false);

        return true;
    }

    private function calculatePoints(int $timeMs, int $responseWindowMs): int
    {
        $normalized = min(max($timeMs, 0), $responseWindowMs);
        $timeBonus = max(0, ($responseWindowMs - $normalized) / $responseWindowMs);

        return (int) (self::BASE_POINTS * (0.5 + (0.5 * $timeBonus)));
    }

    private function buildRoundSummary(GameSession $session, array $state): array
    {
        $participants = $this->gameSessionGuestRepository->findBySession($session);
        $answers = is_array($state['answers'] ?? null) ? $state['answers'] : [];

        return [
            'round' => isset($state['round']) ? (int) $state['round'] : 1,
            'answersCount' => count($answers),
            'closeReason' => $state['roundCloseReason'] ?? null,
            'participants' => array_map(fn(GameSessionGuest $item) => [
                'id' => $item->getId(),
                'guestId' => $item->getGuest()?->getId(),
                'nickname' => $item->getGuest()?->getNickName(),
                'score' => $item->getScore(),
                'lives' => $item->getLives(),
                'isAlive' => $item->isAlive(),
            ], $participants),
        ];
    }

    /**
     * @param GameSessionGuest[] $participants
     */
    private function buildRanking(array $participants): array
    {
        usort($participants, function (GameSessionGuest $a, GameSessionGuest $b): int {
            if ($a->getScore() !== $b->getScore()) {
                return $b->getScore() <=> $a->getScore();
            }

            if ($a->getLives() !== $b->getLives()) {
                return $b->getLives() <=> $a->getLives();
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return array_map(fn(GameSessionGuest $participant, int $index) => [
            'position' => $index + 1,
            'id' => $participant->getId(),
            'guestId' => $participant->getGuest()?->getId(),
            'nickname' => $participant->getGuest()?->getNickName(),
            'score' => $participant->getScore(),
            'lives' => $participant->getLives(),
            'isAlive' => $participant->isAlive(),
        ], $participants, array_keys($participants));
    }

    private function serializeSession(GameSession $session): array
    {
        return [
            'id' => $session->getId(),
            'status' => $session->getStatus()?->value,
            'difficulty' => $session->getDifficulty()?->value,
            'userId' => $session->getUserId(),
            'state' => $session->getState(),
        ];
    }
}
