<?php

use App\Entity\BattlePlayer;
use App\Entity\Guest;
use App\Entity\File;
use App\Service\WeaknessAlgorithmService;

beforeEach(function () {
    $this->service = new WeaknessAlgorithmService();
});

function makePlayer(?array $weaknessProfile): BattlePlayer
{
    $guest = new Guest();
    $guest->setNickName('tester');
    $guest->setAvatar(new File());
    $guest->setWeaknessProfile($weaknessProfile);

    $player = new BattlePlayer();
    $player->setGuest($guest);

    return $player;
}

describe('selectQuestion', function () {
    it('retorna uma operação válida quando não há perfil de fraqueza', function () {
        $player = makePlayer(null);

        $operation = $this->service->selectQuestion([$player]);

        expect($operation)->toMatch('/^\d+x\d+$/');
    });

    it('ignora elementos que não são BattlePlayer', function () {
        $operation = $this->service->selectQuestion([new stdClass(), 'foo']);

        expect($operation)->toMatch('/^\d+x\d+$/');
    });

    it('considera operações mesmo quando combinadas têm score zero', function () {
        $profile = [
            '5x5' => ['attempts' => 5, 'correct' => 5, 'avgTimeMs' => 0],
        ];
        $player = makePlayer($profile);

        $operation = $this->service->selectQuestion([$player]);

        expect($operation)->toMatch('/^\d+x\d+$/');
    });
});

describe('generateAnswerOptions', function () {
    it('calcula correctAnswer e gera 4 opções distintas', function () {
        $result = $this->service->generateAnswerOptions('6x7');

        expect($result)
            ->toHaveKeys(['operation', 'correctAnswer', 'options'])
            ->and($result['operation'])->toBe('6x7')
            ->and($result['correctAnswer'])->toBe(42)
            ->and($result['options'])->toHaveCount(4)
            ->and(array_unique($result['options']))->toHaveCount(4)
            ->and($result['options'])->toContain(42);

        foreach ($result['options'] as $option) {
            expect($option)->toBeInt()->toBeGreaterThan(0);
        }
    });
});

describe('generateHint', function () {
    it('produz um intervalo que contém o resultado correto', function () {
        $hint = $this->service->generateHint('8x9');

        preg_match('/entre (\d+) e (\d+)/', $hint, $m);

        expect($m)->toHaveCount(3);
        $lower = (int) $m[1];
        $upper = (int) $m[2];

        expect(72)->toBeGreaterThanOrEqual($lower)->toBeLessThanOrEqual($upper);
    });

    it('nunca retorna lower negativo', function () {
        for ($i = 0; $i < 30; $i++) {
            $hint = $this->service->generateHint('2x2');
            preg_match('/entre (-?\d+) e (\d+)/', $hint, $m);
            expect((int) $m[1])->toBeGreaterThanOrEqual(0);
        }
    });
});

describe('generateReducedOptions', function () {
    it('reduz para apenas 2 opções, mantendo a correta', function () {
        $result = $this->service->generateReducedOptions('3x4');

        expect($result['operation'])->toBe('3x4');
        expect($result['correctAnswer'])->toBe(12);
        expect($result['options'])
            ->toHaveCount(2)
            ->toContain(12);
        expect(array_unique($result['options']))->toHaveCount(2);
    });
});
