<?php

namespace App\Controller;

use App\Entity\GameSession;
use App\Entity\GameSessionGuest;
use App\Entity\User;
use App\Event\GameParticipantUpdatedEvent;
use App\Repository\GameSessionGuestRepository;
use App\Repository\GameSessionRepository;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/api/game-sessions/{sessionId}/guests', name: 'api_game_session_guests_')]
class GameSessionGuestController extends AbstractController
{
    public function __construct(
        private GameSessionRepository $gameSessionRepository,
        private GameSessionGuestRepository $gameSessionGuestRepository,
        private GuestRepository $guestRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $sessionId, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($sessionId, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $items = $this->gameSessionGuestRepository->findBy(['gameSession' => $session], ['id' => 'ASC']);

        return $this->json(array_map(
            fn(GameSessionGuest $item) => $this->serializeGameSessionGuest($item),
            $items
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $sessionId, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($sessionId, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $guestId = $this->parsePositiveInt($payload['guestId'] ?? null);
        if (!$guestId) {
            return $this->json(['message' => 'guestId must be a positive integer.'], Response::HTTP_BAD_REQUEST);
        }

        $guest = $this->guestRepository->find($guestId);
        if (!$guest) {
            return $this->json(['message' => 'Guest not found.'], Response::HTTP_NOT_FOUND);
        }

        $score = array_key_exists('score', $payload) ? $this->parseInt($payload['score']) : 0;
        if (null === $score) {
            return $this->json(['message' => 'score must be an integer.'], Response::HTTP_BAD_REQUEST);
        }

        $lives = array_key_exists('lives', $payload) ? $this->parseInt($payload['lives']) : 3;
        if (null === $lives) {
            return $this->json(['message' => 'lives must be an integer.'], Response::HTTP_BAD_REQUEST);
        }

        $isAlive = array_key_exists('isAlive', $payload) ? $this->parseBool($payload['isAlive']) : true;
        if (null === $isAlive) {
            return $this->json(['message' => 'isAlive must be a boolean.'], Response::HTTP_BAD_REQUEST);
        }

        $item = (new GameSessionGuest())
            ->setGameSession($session)
            ->setGuest($guest)
            ->setScore($score)
            ->setLives($lives)
            ->setIsAlive($isAlive);

        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new GameParticipantUpdatedEvent($session, $item));

        return $this->json($this->serializeGameSessionGuest($item), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $sessionId, int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($sessionId, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $item = $this->gameSessionGuestRepository->findOneBy([
            'id' => $id,
            'gameSession' => $session,
        ]);

        if (!$item) {
            return $this->json(['message' => 'Game session guest not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeGameSessionGuest($item));
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $sessionId, int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($sessionId, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $item = $this->gameSessionGuestRepository->findOneBy([
            'id' => $id,
            'gameSession' => $session,
        ]);

        if (!$item) {
            return $this->json(['message' => 'Game session guest not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('guestId', $payload)) {
            $guestId = $this->parsePositiveInt($payload['guestId']);
            if (!$guestId) {
                return $this->json(['message' => 'guestId must be a positive integer.'], Response::HTTP_BAD_REQUEST);
            }

            $guest = $this->guestRepository->find($guestId);
            if (!$guest) {
                return $this->json(['message' => 'Guest not found.'], Response::HTTP_NOT_FOUND);
            }

            $item->setGuest($guest);
        }

        if (array_key_exists('score', $payload)) {
            $score = $this->parseInt($payload['score']);
            if (null === $score) {
                return $this->json(['message' => 'score must be an integer.'], Response::HTTP_BAD_REQUEST);
            }
            $item->setScore($score);
        }

        if (array_key_exists('lives', $payload)) {
            $lives = $this->parseInt($payload['lives']);
            if (null === $lives) {
                return $this->json(['message' => 'lives must be an integer.'], Response::HTTP_BAD_REQUEST);
            }
            $item->setLives($lives);
        }

        if (array_key_exists('isAlive', $payload)) {
            $isAlive = $this->parseBool($payload['isAlive']);
            if (null === $isAlive) {
                return $this->json(['message' => 'isAlive must be a boolean.'], Response::HTTP_BAD_REQUEST);
            }
            $item->setIsAlive($isAlive);
        }

        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new GameParticipantUpdatedEvent($session, $item));

        return $this->json($this->serializeGameSessionGuest($item));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $sessionId, int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $session = $this->getOwnedSession($sessionId, $user);
        if (!$session) {
            return $this->json(['message' => 'Game session not found.'], Response::HTTP_NOT_FOUND);
        }

        $item = $this->gameSessionGuestRepository->findOneBy([
            'id' => $id,
            'gameSession' => $session,
        ]);

        if (!$item) {
            return $this->json(['message' => 'Game session guest not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return $this->json(['message' => 'Game session guest deleted.']);
    }

    private function getOwnedSession(int $sessionId, User $user): ?GameSession
    {
        return $this->gameSessionRepository->findOneBy([
            'id' => $sessionId,
            'userId' => $user->getId(),
        ]);
    }

    private function serializeGameSessionGuest(GameSessionGuest $item): array
    {
        return [
            'id' => $item->getId(),
            'gameSessionId' => $item->getGameSession()?->getId(),
            'guest' => [
                'id' => $item->getGuest()?->getId(),
                'nickname' => $item->getGuest()?->getNickName(),
            ],
            'score' => $item->getScore(),
            'lives' => $item->getLives(),
            'isAlive' => $item->isAlive(),
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

    private function parseBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return (bool) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            '1', 'true' => true,
            '0', 'false' => false,
            default => null,
        };
    }
}
