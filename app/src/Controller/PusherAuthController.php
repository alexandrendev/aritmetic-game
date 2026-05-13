<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\GameSessionGuestRepository;
use App\Repository\GameSessionRepository;
use App\Service\PusherPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/pusher')]
class PusherAuthController extends AbstractController
{
    public function __construct(
        private readonly PusherPublisher $publisher,
        private readonly GameSessionRepository $sessionRepository,
        private readonly GameSessionGuestRepository $sessionGuestRepository,
    ) {}

    #[Route('/auth', name: 'api_pusher_auth', methods: ['POST'])]
    public function auth(Request $request, #[CurrentUser] ?User $user): Response
    {
        $socketId    = $request->request->get('socket_id');
        $channelName = $request->request->get('channel_name');

        if (!$socketId || !$channelName) {
            return new Response(
                json_encode(['message' => 'socket_id and channel_name are required']),
                Response::HTTP_BAD_REQUEST,
                ['Content-Type' => 'application/json']
            );
        }

        // Host path: authenticated via JWT
        if ($user) {
            $auth = $this->publisher->authenticate($socketId, $channelName);
            return new Response($auth, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        // Guest path: validate guest_session_id against the session in the channel name
        $guestSessionId = $request->request->get('guest_session_id');

        if (!$guestSessionId || !preg_match('/^private-game-session-(\d+)$/', $channelName, $matches)) {
            return new Response(
                json_encode(['message' => 'Unauthorized']),
                Response::HTTP_UNAUTHORIZED,
                ['Content-Type' => 'application/json']
            );
        }

        $sessionId = (int) $matches[1];
        $session   = $this->sessionRepository->find($sessionId);

        if (!$session) {
            return new Response(
                json_encode(['message' => 'Session not found']),
                Response::HTTP_NOT_FOUND,
                ['Content-Type' => 'application/json']
            );
        }

        $sessionGuest = $this->sessionGuestRepository->findOneBySessionAndId($session, (int) $guestSessionId);

        if (!$sessionGuest) {
            return new Response(
                json_encode(['message' => 'Unauthorized']),
                Response::HTTP_UNAUTHORIZED,
                ['Content-Type' => 'application/json']
            );
        }

        $auth = $this->publisher->authenticate($socketId, $channelName);
        return new Response($auth, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }
}
