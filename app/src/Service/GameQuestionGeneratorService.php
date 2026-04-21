<?php

namespace App\Service;

use App\Entity\Difficulty;

class GameQuestionGeneratorService
{
    public const MIN_TARGET = 1;
    public const MAX_TARGET = 30;
    public const MIN_MULTIPLIER = 1;
    public const MAX_MULTIPLIER = 10;

    public function resolveTarget(?int $target = null): int
    {
        if (null === $target) {
            return random_int(self::MIN_TARGET, self::MAX_TARGET);
        }

        if ($target < self::MIN_TARGET || $target > self::MAX_TARGET) {
            throw new \InvalidArgumentException('target must be between 1 and 30.');
        }

        return $target;
    }

    public function resolveDifficultyByTarget(int $target): Difficulty
    {
        if ($target < self::MIN_TARGET || $target > self::MAX_TARGET) {
            throw new \InvalidArgumentException('target must be between 1 and 30.');
        }

        return match (true) {
            $target <= 10 => Difficulty::EASY,
            $target <= 20 => Difficulty::MEDIUM,
            default => Difficulty::HARD,
        };
    }

    public function generateQuestion(int $target, int $round, int $timeoutMs): array
    {
        $multiplier = random_int(self::MIN_MULTIPLIER, self::MAX_MULTIPLIER);
        $correctAnswer = $multiplier * $target;

        return [
            'id' => sprintf('q-r%d-%s', $round, bin2hex(random_bytes(4))),
            'round' => $round,
            'target' => $target,
            'multiplier' => $multiplier,
            'operation' => sprintf('%dx%d', $multiplier, $target),
            'correctAnswer' => $correctAnswer,
            'options' => $this->generateOptions($correctAnswer),
            'timeoutMs' => $timeoutMs,
        ];
    }

    /**
     * @return int[]
     */
    private function generateOptions(int $correctAnswer): array
    {
        $options = [$correctAnswer];

        while (count($options) < 4) {
            $offset = random_int(-15, 15);
            $wrong = $correctAnswer + $offset;

            if ($wrong <= 0 || in_array($wrong, $options, true)) {
                continue;
            }

            $options[] = $wrong;
        }

        shuffle($options);

        return $options;
    }
}
