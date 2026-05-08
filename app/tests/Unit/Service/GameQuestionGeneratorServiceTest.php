<?php

use App\Entity\Difficulty;
use App\Service\GameQuestionGeneratorService;

beforeEach(function () {
    $this->service = new GameQuestionGeneratorService();
});

describe('resolveTarget', function () {
    it('retorna um valor dentro do intervalo permitido quando target é nulo', function () {
        $target = $this->service->resolveTarget(null);

        expect($target)
            ->toBeInt()
            ->toBeGreaterThanOrEqual(GameQuestionGeneratorService::MIN_TARGET)
            ->toBeLessThanOrEqual(GameQuestionGeneratorService::MAX_TARGET);
    });

    it('retorna o valor passado quando dentro do intervalo', function () {
        expect($this->service->resolveTarget(1))->toBe(1);
        expect($this->service->resolveTarget(15))->toBe(15);
        expect($this->service->resolveTarget(30))->toBe(30);
    });

    it('lança exceção para targets fora do intervalo', function (int $invalid) {
        expect(fn () => $this->service->resolveTarget($invalid))
            ->toThrow(InvalidArgumentException::class, 'target must be between 1 and 30.');
    })->with([0, -1, 31, 100]);
});

describe('resolveDifficultyByTarget', function () {
    it('classifica corretamente as faixas de dificuldade', function (int $target, Difficulty $expected) {
        expect($this->service->resolveDifficultyByTarget($target))->toBe($expected);
    })->with([
        [1, Difficulty::EASY],
        [10, Difficulty::EASY],
        [11, Difficulty::MEDIUM],
        [20, Difficulty::MEDIUM],
        [21, Difficulty::HARD],
        [30, Difficulty::HARD],
    ]);

    it('lança exceção para alvos inválidos', function () {
        expect(fn () => $this->service->resolveDifficultyByTarget(0))
            ->toThrow(InvalidArgumentException::class);
        expect(fn () => $this->service->resolveDifficultyByTarget(31))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('generateQuestion', function () {
    it('gera uma questão com a estrutura esperada', function () {
        $question = $this->service->generateQuestion(target: 5, round: 2, timeoutMs: 8000);

        expect($question)
            ->toHaveKeys(['id', 'round', 'target', 'multiplier', 'operation', 'correctAnswer', 'options', 'timeoutMs'])
            ->and($question['round'])->toBe(2)
            ->and($question['target'])->toBe(5)
            ->and($question['timeoutMs'])->toBe(8000)
            ->and($question['id'])->toStartWith('q-r2-');
    });

    it('mantém coerência aritmética entre multiplier, target e correctAnswer', function () {
        $question = $this->service->generateQuestion(target: 7, round: 1, timeoutMs: 5000);

        expect($question['correctAnswer'])->toBe($question['multiplier'] * 7);
        expect($question['operation'])->toBe(sprintf('%dx7', $question['multiplier']));
        expect($question['multiplier'])
            ->toBeGreaterThanOrEqual(GameQuestionGeneratorService::MIN_MULTIPLIER)
            ->toBeLessThanOrEqual(GameQuestionGeneratorService::MAX_MULTIPLIER);
    });

    it('gera 4 opções únicas, todas positivas, contendo a resposta correta', function () {
        $question = $this->service->generateQuestion(target: 4, round: 1, timeoutMs: 5000);

        expect($question['options'])
            ->toBeArray()
            ->toHaveCount(4);

        expect(array_unique($question['options']))->toHaveCount(4);
        expect($question['options'])->toContain($question['correctAnswer']);

        foreach ($question['options'] as $option) {
            expect($option)->toBeInt()->toBeGreaterThan(0);
        }
    });
});
