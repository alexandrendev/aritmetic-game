<?php

namespace App\Service;

use App\Entity\BattlePlayer;
use Doctrine\ORM\EntityManagerInterface;

class ToolService
{
    public function __construct(
        private WeaknessAlgorithmService $weaknessAlgorithm,
        private EntityManagerInterface $entityManager
    ) {}

    public function useTool(BattlePlayer $player, string $toolName, string $operation): array
    {
        if (!$player->isAlive()) {
            throw new \RuntimeException('Jogador eliminado não pode usar ferramentas.');
        }

        $result = match ($toolName) {
            'hint' => $this->useHint($player, $operation),
            'eliminate' => $this->useEliminate($player, $operation),
            'skip' => $this->useSkip($player),
            default => throw new \InvalidArgumentException("Ferramenta '{$toolName}' não existe."),
        };

        $this->entityManager->flush();

        return $result;
    }

    private function useHint(BattlePlayer $player, string $operation): array
    {
        if (!$player->hasToolHint()) {
            throw new \RuntimeException('Ferramenta "dica" já foi usada.');
        }

        $player->useToolHint();

        return [
            'tool' => 'hint',
            'hint' => $this->weaknessAlgorithm->generateHint($operation),
        ];
    }

    private function useEliminate(BattlePlayer $player, string $operation): array
    {
        if (!$player->hasToolEliminate()) {
            throw new \RuntimeException('Ferramenta "eliminar opções" já foi usada.');
        }

        $player->useToolEliminate();

        return [
            'tool' => 'eliminate',
            'reducedOptions' => $this->weaknessAlgorithm->generateReducedOptions($operation),
        ];
    }

    private function useSkip(BattlePlayer $player): array
    {
        if (!$player->hasToolSkip()) {
            throw new \RuntimeException('Ferramenta "pular" já foi usada.');
        }

        $player->useToolSkip();

        return [
            'tool' => 'skip',
            'skipped' => true,
        ];
    }
}
