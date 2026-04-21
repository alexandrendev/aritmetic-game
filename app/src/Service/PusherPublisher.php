<?php

namespace App\Service;

use Pusher\Pusher;

class PusherPublisher
{
    private ?Pusher $client = null;

    public function __construct(
        bool $enabled,
        string $appId,
        string $appKey,
        string $appSecret,
        string $appCluster,
    ) {
        if (!$enabled) {
            return;
        }

        if ($appId === '' || $appKey === '' || $appSecret === '' || $appCluster === '') {
            throw new \InvalidArgumentException(
                'Pusher is enabled, but credentials are missing. Configure PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET and PUSHER_APP_CLUSTER.'
            );
        }

        $this->client = new Pusher(
            $appKey,
            $appSecret,
            $appId,
            [
                'cluster' => $appCluster,
                'useTLS' => true,
            ]
        );
    }

    public function publishToSession(int $sessionId, string $eventName, array $payload): void
    {
        $this->publish(sprintf('private-game-session-%d', $sessionId), $eventName, $payload);
    }

    public function publishToUser(int $userId, string $eventName, array $payload): void
    {
        $this->publish(sprintf('private-user-%d', $userId), $eventName, $payload);
    }

    private function publish(string $channel, string $eventName, array $payload): void
    {
        if (!$this->client) {
            return;
        }

        $this->client->trigger($channel, $eventName, array_merge(
            [
                'schemaVersion' => 1,
                'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
            $payload
        ));
    }
}
