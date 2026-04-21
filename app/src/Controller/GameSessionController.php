<?php

namespace App\Controller;

use App\Entity\Difficulty;
use App\Entity\GameSession;
use App\Entity\Status;
use App\Entity\User;
use App\Event\GameSessionCreatedEvent;
use App\Repository\GameSessionGuestRepository;
use App\Repository\GameSessionRepository;
use App\Service\GameSessionEngineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/api/game-sessions', name: 'api_game_sessions_')]
class GameSessionController extends AbstractController
{
    public function __construct(
        private GameSessionRepository $gameSessionRepository,
        private GameSessionGuestRepository $gameSessionGuestRepository,
        private GameSessionEngineService $gameSessionEngineService,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $sessions = $this->gameSessionRepository->findBy(['userId' => $user->getId()], ['id' => 'DESC']);

        return $this->json(array_map(
            fn(GameSession $session) => $this->serializeGameSession($session),
            $sessions
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);
        if (null === $payload) {
            $payload = [];
        }
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $status = array_key_exists('status', $payload) && is_string($payload['status'])
            ? Status::tryFrom($payload['status'])
            : Status::WAITING;
        if (!$status) {
            return $this->json([
                'message' => 'Invalid status.',
                'allowed' => array_map(fn(Status $item) => $item->value, Status::cases()),
            ], Response::HTTP_BAD_REQUEST);
        }

        $difficulty = array_key_exists('difficulty', $payload) && is_string($payload['difficulty'])
            ? Difficulty::tryFrom($payload['difficulty'])
            : Difficulty::EASY;
        if (!$difficulty) {
            return $this->json([
                'message' => 'Invalid difficulty.',
                'allowed' => array_map(fn(Difficulty $item) => $item->value, Difficulty::cases()),
            ], Response::HTTP_BAD_REQUEST);
        }

        $state = $payload['state'] ?? null;
        if (!is_array($state) && null !== $state) {
            return $this->json(['message' => 'state must be an object/array or null.'], Response::HTTP_BAD_REQUEST);
        }

        $session = (new GameSession())
            ->setUserId((int) $user->getId())
            ->setStatus($status)
            ->setDifficulty($difficulty)
            ->setState($state);

        $this->entityManager->persist($session);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new GameSessionCreatedEvent($session));

        return $this->json($this->serializeGameSession($session), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->gameSessionRepository->findOneBy([
            'id' => $id,
            'userId' => $user->getId(),
        ]);

        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeGameSession($session));
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($id, $user);

        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (null === $payload) {
            $payload = [];
        }
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('status', $payload)) {
            $status = is_string($payload['status']) ? Status::tryFrom($payload['status']) : null;
            if (!$status) {
                return $this->json([
                    'message' => 'Invalid status.',
                    'allowed' => array_map(fn(Status $item) => $item->value, Status::cases()),
                ], Response::HTTP_BAD_REQUEST);
            }
            $session->setStatus($status);
        }

        if (array_key_exists('difficulty', $payload)) {
            $difficulty = is_string($payload['difficulty']) ? Difficulty::tryFrom($payload['difficulty']) : null;
            if (!$difficulty) {
                return $this->json([
                    'message' => 'Invalid difficulty.',
                    'allowed' => array_map(fn(Difficulty $item) => $item->value, Difficulty::cases()),
                ], Response::HTTP_BAD_REQUEST);
            }
            $session->setDifficulty($difficulty);
        }

        if (array_key_exists('state', $payload)) {
            if (!is_array($payload['state']) && null !== $payload['state']) {
                return $this->json(['message' => 'state must be an object/array or null.'], Response::HTTP_BAD_REQUEST);
            }

            $session->setState($payload['state']);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeGameSession($session));
    }

    #[Route('/{id}/start', name: 'start', methods: ['POST'])]
    public function start(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($id, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (null === $payload) {
            $payload = [];
        }
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $target = null;
        if (array_key_exists('target', $payload)) {
            $target = $this->parsePositiveInt($payload['target']);
            if (null === $target || $target > 30) {
                return $this->json(['message' => 'target must be an integer between 1 and 30.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $totalRounds = null;
        if (array_key_exists('totalRounds', $payload)) {
            $totalRounds = $this->parsePositiveInt($payload['totalRounds']);
            if (null === $totalRounds) {
                return $this->json(['message' => 'totalRounds must be a positive integer.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $responseWindowMs = null;
        if (array_key_exists('responseWindowMs', $payload)) {
            $responseWindowMs = $this->parsePositiveInt($payload['responseWindowMs']);
            if (null === $responseWindowMs) {
                return $this->json(['message' => 'responseWindowMs must be a positive integer.'], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $result = $this->gameSessionEngineService->startSession(
                $session,
                $target,
                $totalRounds,
                $responseWindowMs
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($result);
    }

    #[Route('/{id}/answer', name: 'answer', methods: ['POST'])]
    public function answer(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($id, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $participantId = $this->parsePositiveInt($payload['gameSessionGuestId'] ?? null);
        if (null === $participantId) {
            return $this->json(['message' => 'gameSessionGuestId must be a positive integer.'], Response::HTTP_BAD_REQUEST);
        }

        $participant = $this->gameSessionGuestRepository->findOneBySessionAndId($session, $participantId);
        if (!$participant) {
            return $this->json(['message' => 'Game session participant not found.'], Response::HTTP_NOT_FOUND);
        }

        $submittedAnswer = $this->parseInt($payload['answer'] ?? null);
        if (null === $submittedAnswer) {
            return $this->json(['message' => 'answer must be an integer.'], Response::HTTP_BAD_REQUEST);
        }

        $timeMs = $this->parseNonNegativeInt($payload['timeMs'] ?? null);
        if (null === $timeMs) {
            return $this->json(['message' => 'timeMs must be a non-negative integer.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->gameSessionEngineService->answerCurrentQuestion(
                $session,
                $participant,
                $submittedAnswer,
                $timeMs
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($result);
    }

    #[Route('/{id}/next-round', name: 'next_round', methods: ['POST'])]
    public function nextRound(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'message' => 'Round progression is automatic. Listen to Pusher events for next question rendering.',
        ], Response::HTTP_GONE);
    }

    #[Route('/{id}/finish', name: 'finish', methods: ['POST'])]
    public function finish(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($id, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $reason = 'manual';

        if (is_array($payload) && array_key_exists('reason', $payload)) {
            if (!is_string($payload['reason']) || trim($payload['reason']) === '') {
                return $this->json(['message' => 'reason must be a non-empty string.'], Response::HTTP_BAD_REQUEST);
            }
            $reason = trim($payload['reason']);
        }

        $result = $this->gameSessionEngineService->finishSession($session, $reason);

        return $this->json($result);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($id, $user);

        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($session);
        $this->entityManager->flush();

        return $this->json(['message' => 'Game session deleted.']);
    }

    private function getOwnedSession(int $sessionId, User $user): ?GameSession
    {
        return $this->gameSessionRepository->findOneBy([
            'id' => $sessionId,
            'userId' => $user->getId(),
        ]);
    }

    private function serializeGameSession(GameSession $session): array
    {
        return [
            'id' => $session->getId(),
            'status' => $session->getStatus()?->value,
            'state' => $session->getState(),
            'userId' => $session->getUserId(),
            'difficulty' => $session->getDifficulty()?->value,
        ];
    }

    private function parseInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        $parsed = $this->parseInt($value);

        if (null === $parsed || $parsed <= 0) {
            return null;
        }

        return $parsed;
    }

    private function parseNonNegativeInt(mixed $value): ?int
    {
        $parsed = $this->parseInt($value);

        if (null === $parsed || $parsed < 0) {
            return null;
        }

        return $parsed;
    }
}
