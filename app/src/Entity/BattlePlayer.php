<?php

namespace App\Entity;

use App\Repository\BattlePlayerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BattlePlayerRepository::class)]
#[ORM\Table(name: 'battle_players')]
#[ORM\HasLifecycleCallbacks]
class BattlePlayer
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_READY = 'ready';
    public const STATUS_PLAYING = 'playing';
    public const STATUS_ELIMINATED = 'eliminated';
    public const STATUS_GHOST = 'ghost';

    public const INITIAL_LIVES = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BattleRoom::class, inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BattleRoom $battleRoom = null;

    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guest $guest = null;

    #[ORM\Column]
    private int $lives = self::INITIAL_LIVES;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_WAITING;

    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column]
    private bool $toolHint = true;

    #[ORM\Column]
    private bool $toolEliminate = true;

    #[ORM\Column]
    private bool $toolSkip = true;

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

    public function getBattleRoom(): ?BattleRoom
    {
        return $this->battleRoom;
    }

    public function setBattleRoom(BattleRoom $battleRoom): static
    {
        $this->battleRoom = $battleRoom;

        return $this;
    }

    public function getGuest(): ?Guest
    {
        return $this->guest;
    }

    public function setGuest(Guest $guest): static
    {
        $this->guest = $guest;

        return $this;
    }

    public function getLives(): int
    {
        return $this->lives;
    }

    public function loseLife(): static
    {
        $this->lives = max(0, $this->lives - 1);

        if ($this->lives === 0) {
            $this->status = self::STATUS_GHOST;
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function addScore(int $points): static
    {
        $this->score += $points;

        return $this;
    }

    public function hasToolHint(): bool
    {
        return $this->toolHint;
    }

    public function useToolHint(): static
    {
        $this->toolHint = false;

        return $this;
    }

    public function hasToolEliminate(): bool
    {
        return $this->toolEliminate;
    }

    public function useToolEliminate(): static
    {
        $this->toolEliminate = false;

        return $this;
    }

    public function hasToolSkip(): bool
    {
        return $this->toolSkip;
    }

    public function useToolSkip(): static
    {
        $this->toolSkip = false;

        return $this;
    }

    public function getToolsUsedCount(): int
    {
        $used = 0;
        if (!$this->toolHint) $used++;
        if (!$this->toolEliminate) $used++;
        if (!$this->toolSkip) $used++;

        return $used;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAlive(): bool
    {
        return $this->lives > 0;
    }
}
