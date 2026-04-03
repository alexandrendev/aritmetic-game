<?php

namespace App\Service;

use App\Entity\BattlePlayer;
use App\Entity\Guest;

class WeaknessAlgorithmService
{
    private const ALL_OPERATIONS = [
        '2x2', '2x3', '2x4', '2x5', '2x6', '2x7', '2x8', '2x9', '2x10',
        '3x3', '3x4', '3x5', '3x6', '3x7', '3x8', '3x9', '3x10',
        '4x4', '4x5', '4x6', '4x7', '4x8', '4x9', '4x10',
        '5x5', '5x6', '5x7', '5x8', '5x9', '5x10',
        '6x6', '6x7', '6x8', '6x9', '6x10',
        '7x7', '7x8', '7x9', '7x10',
        '8x8', '8x9', '8x10',
        '9x9', '9x10',
        '10x10',
    ];

    private const WEAKNESS_WEIGHT = 0.7;
    private const RANDOM_WEIGHT = 0.3;

    public function selectQuestion(array $players): string
    {
        $weaknesses = $this->combineWeaknesses($players);

        if (empty($weaknesses)) {
            return self::ALL_OPERATIONS[array_rand(self::ALL_OPERATIONS)];
        }

        // 70% chance: pick from weaknesses, 30% chance: random
        if (mt_rand(1, 100) <= self::WEAKNESS_WEIGHT * 100) {
            return $this->pickWeighted($weaknesses);
        }

        return self::ALL_OPERATIONS[array_rand(self::ALL_OPERATIONS)];
    }

    private function combineWeaknesses(array $players): array
    {
        $combined = [];

        foreach ($players as $player) {
            if (!$player instanceof BattlePlayer) {
                continue;
            }

            $profile = $player->getGuest()->getWeaknessProfile();
            if ($profile === null) {
                continue;
            }

            foreach ($profile as $operation => $data) {
                $attempts = $data['attempts'] ?? 0;
                $correct = $data['correct'] ?? 0;

                if ($attempts === 0) {
                    continue;
                }

                $errorRate = 1 - ($correct / $attempts);
                $avgTime = $data['avgTimeMs'] ?? 0;
                $timeScore = min($avgTime / 10000, 1.0);

                $score = ($errorRate * 0.6) + ($timeScore * 0.4);

                if (!isset($combined[$operation])) {
                    $combined[$operation] = 0;
                }

                $combined[$operation] += $score;
            }
        }

        arsort($combined);

        return $combined;
    }

    private function pickWeighted(array $scoredOperations): string
    {
        $total = array_sum($scoredOperations);

        if ($total <= 0) {
            return self::ALL_OPERATIONS[array_rand(self::ALL_OPERATIONS)];
        }

        $rand = mt_rand(1, (int)($total * 1000)) / 1000;
        $cumulative = 0;

        foreach ($scoredOperations as $operation => $score) {
            $cumulative += $score;
            if ($rand <= $cumulative) {
                return $operation;
            }
        }

        return array_key_first($scoredOperations);
    }

    public function generateAnswerOptions(string $operation): array
    {
        [$a, $b] = explode('x', $operation);
        $correctAnswer = (int)$a * (int)$b;

        $options = [$correctAnswer];

        while (count($options) < 4) {
            $offset = mt_rand(-10, 10);
            $wrong = $correctAnswer + $offset;

            if ($wrong !== $correctAnswer && $wrong > 0 && !in_array($wrong, $options)) {
                $options[] = $wrong;
            }
        }

        shuffle($options);

        return [
            'operation' => $operation,
            'correctAnswer' => $correctAnswer,
            'options' => $options,
        ];
    }

    public function generateHint(string $operation): string
    {
        [$a, $b] = explode('x', $operation);
        $result = (int)$a * (int)$b;
        $lower = max(0, $result - mt_rand(3, 8));
        $upper = $result + mt_rand(3, 8);

        return "O resultado está entre {$lower} e {$upper}";
    }

    public function generateReducedOptions(string $operation): array
    {
        $data = $this->generateAnswerOptions($operation);
        $correct = $data['correctAnswer'];

        $wrongOptions = array_filter($data['options'], fn($o) => $o !== $correct);
        $keptWrong = array_slice($wrongOptions, 0, 1);

        $reduced = array_merge([$correct], $keptWrong);
        shuffle($reduced);

        return [
            'operation' => $operation,
            'correctAnswer' => $correct,
            'options' => $reduced,
        ];
    }
}
