<?php

namespace App\Controller;

use App\Entity\BattleRoom;
use App\Repository\BattleRoomRepository;
use App\Repository\GuestRepository;
use App\Service\BattleService;
use App\Service\ToolService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/battle')]
class BattleRoomController extends AbstractController
{
    public function __construct(
        private BattleService $battleService,
        private ToolService $toolService,
        private BattleRoomRepository $roomRepository,
        private GuestRepository $guestRepository,
    ) {}

    #[Route('/rooms', name: 'battle_room_create', methods: ['POST'])]
    public function createRoom(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $guest = $this->guestRepository->find($body['guestId'] ?? 0);

        if (!$guest) {
            return $this->json(['message' => 'Guest não encontrado.'], 404);
        }

        $room = $this->battleService->createRoom(
            creator: $guest,
            totalRounds: $body['totalRounds'] ?? null,
            maxPlayers: $body['maxPlayers'] ?? null,
        );

        return $this->json($this->serializeRoom($room), 201);
    }

    #[Route('/rooms/{id}', name: 'battle_room_show', methods: ['GET'])]
    public function showRoom(int $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        return $this->json($this->serializeRoom($room));
    }

    #[Route('/rooms/{id}/join', name: 'battle_room_join', methods: ['POST'])]
    public function joinRoom(int $id, Request $request): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $guest = $this->guestRepository->find($body['guestId'] ?? 0);

        if (!$guest) {
            return $this->json(['message' => 'Guest não encontrado.'], 404);
        }

        try {
            $player = $this->battleService->joinRoom($room, $guest);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json([
            'playerId' => $player->getId(),
            'room' => $this->serializeRoom($room),
        ]);
    }

    #[Route('/rooms/{id}/ready', name: 'battle_room_ready', methods: ['POST'])]
    public function setReady(int $id, Request $request): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $playerId = $body['playerId'] ?? 0;

        $player = null;
        foreach ($room->getPlayers() as $p) {
            if ($p->getId() === $playerId) {
                $player = $p;
                break;
            }
        }

        if (!$player) {
            return $this->json(['message' => 'Jogador não encontrado nesta sala.'], 404);
        }

        $this->battleService->setPlayerReady($player);

        return $this->json([
            'playerId' => $player->getId(),
            'status' => $player->getStatus(),
            'allReady' => $room->allPlayersReady(),
        ]);
    }

    #[Route('/rooms/{id}/start', name: 'battle_room_start', methods: ['POST'])]
    public function startBattle(int $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        try {
            $result = $this->battleService->startBattle($room);
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json([
            'status' => 'started',
            'room' => $this->serializeRoom($room),
            'question' => $result,
        ]);
    }

    #[Route('/rooms/{id}/answer', name: 'battle_room_answer', methods: ['POST'])]
    public function submitAnswer(int $id, Request $request): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $playerId = $body['playerId'] ?? 0;

        $player = null;
        foreach ($room->getPlayers() as $p) {
            if ($p->getId() === $playerId) {
                $player = $p;
                break;
            }
        }

        if (!$player) {
            return $this->json(['message' => 'Jogador não encontrado nesta sala.'], 404);
        }

        try {
            $result = $this->battleService->processAnswer(
                player: $player,
                operation: $body['operation'] ?? '',
                answer: $body['answer'] ?? '',
                timeMs: $body['timeMs'] ?? 0,
                usedTool: $body['usedTool'] ?? false,
            );
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json($result);
    }

    #[Route('/rooms/{id}/next-round', name: 'battle_room_next_round', methods: ['POST'])]
    public function nextRound(int $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        $result = $this->battleService->advanceRound($room);

        return $this->json($result);
    }

    #[Route('/rooms/{id}/tool', name: 'battle_room_use_tool', methods: ['POST'])]
    public function useTool(int $id, Request $request): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $playerId = $body['playerId'] ?? 0;

        $player = null;
        foreach ($room->getPlayers() as $p) {
            if ($p->getId() === $playerId) {
                $player = $p;
                break;
            }
        }

        if (!$player) {
            return $this->json(['message' => 'Jogador não encontrado nesta sala.'], 404);
        }

        try {
            $result = $this->toolService->useTool(
                player: $player,
                toolName: $body['tool'] ?? '',
                operation: $body['operation'] ?? '',
            );
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json($result);
    }

    private function serializeRoom(BattleRoom $room): array
    {
        return [
            'id' => $room->getId(),
            'status' => $room->getStatus(),
            'totalRounds' => $room->getTotalRounds(),
            'currentRound' => $room->getCurrentRound(),
            'maxPlayers' => $room->getMaxPlayers(),
            'creator' => [
                'id' => $room->getCreator()->getId(),
                'nickname' => $room->getCreator()->getNickName(),
            ],
            'players' => array_map(fn($p) => [
                'id' => $p->getId(),
                'guestId' => $p->getGuest()->getId(),
                'nickname' => $p->getGuest()->getNickName(),
                'status' => $p->getStatus(),
                'lives' => $p->getLives(),
                'score' => $p->getScore(),
                'tools' => [
                    'hint' => $p->hasToolHint(),
                    'eliminate' => $p->hasToolEliminate(),
                    'skip' => $p->hasToolSkip(),
                ],
            ], $room->getPlayers()->toArray()),
        ];
    }
}
