<?php

namespace App\Entity;

use App\Repository\BattleRoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BattleRoomRepository::class)]
#[ORM\Table(name: 'battle_rooms')]
#[ORM\HasLifecycleCallbacks]
class BattleRoom
{
    public const STATUS_WAITING = 'waiting';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';

    public const MIN_PLAYERS = 2;
    public const MAX_PLAYERS = 60;

    public const ALLOWED_ROUNDS = [5, 10, 15, 20, 25, 30];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guest $creator = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_WAITING;

    #[ORM\Column]
    private int $totalRounds = 10;

    #[ORM\Column]
    private int $currentRound = 0;

    #[ORM\Column]
    private int $maxPlayers = self::MAX_PLAYERS;

    #[ORM\OneToMany(targetEntity: BattlePlayer::class, mappedBy: 'battleRoom', cascade: ['persist', 'remove'])]
    private Collection $players;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->players = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function suggestRounds(int $playerCount): int
    {
        return match (true) {
            $playerCount <= 5 => 10,
            $playerCount <= 15 => 15,
            $playerCount <= 30 => 20,
            default => 25,
        };
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreator(): ?Guest
    {
        return $this->creator;
    }

    public function setCreator(Guest $creator): static
    {
        $this->creator = $creator;

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

    public function getTotalRounds(): int
    {
        return $this->totalRounds;
    }

    public function setTotalRounds(int $totalRounds): static
    {
        $this->totalRounds = $totalRounds;

        return $this;
    }

    public function getCurrentRound(): int
    {
        return $this->currentRound;
    }

    public function setCurrentRound(int $currentRound): static
    {
        $this->currentRound = $currentRound;

        return $this;
    }

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;

        return $this;
    }

    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(BattlePlayer $player): static
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
            $player->setBattleRoom($this);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getAlivePlayers(): array
    {
        return $this->players->filter(
            fn(BattlePlayer $p) => $p->getLives() > 0
        )->toArray();
    }

    public function isRoomFull(): bool
    {
        return $this->players->count() >= $this->maxPlayers;
    }

    public function hasEnoughPlayers(): bool
    {
        return $this->players->count() >= self::MIN_PLAYERS;
    }

    public function allPlayersReady(): bool
    {
        if (!$this->hasEnoughPlayers()) {
            return false;
        }

        return $this->players->forAll(
            fn(int $key, BattlePlayer $p) => $p->getStatus() === BattlePlayer::STATUS_READY
        );
    }
}
