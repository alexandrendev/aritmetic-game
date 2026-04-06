<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
#[ORM\Table(name: 'chat_messages')]
#[ORM\HasLifecycleCallbacks]
class ChatMessage
{
    public const TYPE_MESSAGE = 'message';
    public const TYPE_REACTION = 'reaction';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BattleRoom::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?BattleRoom $room = null;

    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guest $sender = null;

    #[ORM\Column(length: 255)]
    private ?string $content = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_MESSAGE;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoom(): ?BattleRoom
    {
        return $this->room;
    }

    public function setRoom(BattleRoom $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getSender(): ?Guest
    {
        return $this->sender;
    }

    public function setSender(Guest $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
