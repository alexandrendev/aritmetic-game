<?php

namespace App\Controller;

use App\Repository\BattleRoomRepository;
use App\Repository\GuestRepository;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/battle')]
class ChatController extends AbstractController
{
    public function __construct(
        private ChatService $chatService,
        private BattleRoomRepository $roomRepository,
        private GuestRepository $guestRepository,
    ) {}

    #[Route('/rooms/{id}/chat', name: 'battle_chat_send', methods: ['POST'])]
    public function sendMessage(int $id, Request $request): JsonResponse
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
            $message = $this->chatService->sendMessage(
                room: $room,
                sender: $guest,
                content: $body['message'] ?? '',
                type: $body['type'] ?? 'message',
            );
        } catch (\RuntimeException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json([
            'id' => $message->getId(),
            'sender' => [
                'id' => $guest->getId(),
                'nickname' => $guest->getNickName(),
            ],
            'content' => $message->getContent(),
            'type' => $message->getType(),
            'createdAt' => $message->getCreatedAt()->format('c'),
        ], 201);
    }

    #[Route('/rooms/{id}/chat', name: 'battle_chat_list', methods: ['GET'])]
    public function getMessages(int $id): JsonResponse
    {
        $room = $this->roomRepository->find($id);

        if (!$room) {
            return $this->json(['message' => 'Sala não encontrada.'], 404);
        }

        $messages = $this->chatService->getMessages($room);

        return $this->json(array_map(fn($m) => [
            'id' => $m->getId(),
            'sender' => [
                'id' => $m->getSender()->getId(),
                'nickname' => $m->getSender()->getNickName(),
            ],
            'content' => $m->getContent(),
            'type' => $m->getType(),
            'createdAt' => $m->getCreatedAt()->format('c'),
        ], $messages));
    }
}
