<?php

namespace App\Entity;

use App\Repository\GameAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameAnswerRepository::class)]
#[ORM\Table(name: 'game_answers')]
#[ORM\HasLifecycleCallbacks]
class GameAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Guest::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guest $guest = null;

    #[ORM\Column(length: 10)]
    private ?string $operation = null;

    #[ORM\Column]
    private ?bool $correct = null;

    #[ORM\Column]
    private ?int $timeMs = null;

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

    public function getGuest(): ?Guest
    {
        return $this->guest;
    }

    public function setGuest(Guest $guest): static
    {
        $this->guest = $guest;

        return $this;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }

    public function setOperation(string $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function isCorrect(): ?bool
    {
        return $this->correct;
    }

    public function setCorrect(bool $correct): static
    {
        $this->correct = $correct;

        return $this;
    }

    public function getTimeMs(): ?int
    {
        return $this->timeMs;
    }

    public function setTimeMs(int $timeMs): static
    {
        $this->timeMs = $timeMs;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
