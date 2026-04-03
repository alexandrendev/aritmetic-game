<?php

namespace App\Service;

use App\Entity\BattleRoom;
use App\Entity\ChatMessage;
use App\Entity\Guest;
use App\Event\ChatMessageEvent;
use App\Repository\ChatMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ChatService
{
    private const MAX_MESSAGE_LENGTH = 200;

    public function __construct(
        private ChatMessageRepository $chatMessageRepository,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function sendMessage(BattleRoom $room, Guest $sender, string $content, string $type = ChatMessage::TYPE_MESSAGE): ChatMessage
    {
        $content = trim($content);

        if (empty($content)) {
            throw new \RuntimeException('Mensagem não pode estar vazia.');
        }

        if (mb_strlen($content) > self::MAX_MESSAGE_LENGTH) {
            $content = mb_substr($content, 0, self::MAX_MESSAGE_LENGTH);
        }

        $message = new ChatMessage();
        $message->setRoom($room);
        $message->setSender($sender);
        $message->setContent($content);
        $message->setType($type);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new ChatMessageEvent($room, $sender, $content, $type));

        return $message;
    }

    public function getMessages(BattleRoom $room, int $limit = 50): array
    {
        return $this->chatMessageRepository->findByRoom($room->getId(), $limit);
    }
}
