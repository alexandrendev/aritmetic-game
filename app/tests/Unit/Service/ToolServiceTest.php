<?php

use App\Entity\BattlePlayer;
use App\Entity\File;
use App\Entity\Guest;
use App\Service\ToolService;
use App\Service\WeaknessAlgorithmService;
use Doctrine\ORM\EntityManagerInterface;

beforeEach(function () {
    $this->em = Mockery::mock(EntityManagerInterface::class);
    $this->em->shouldReceive('flush')->andReturnNull();

    $this->service = new ToolService(new WeaknessAlgorithmService(), $this->em);

    $guest = new Guest();
    $guest->setNickName('p1');
    $guest->setAvatar(new File());

    $this->player = new BattlePlayer();
    $this->player->setGuest($guest);
});

afterEach(function () {
    Mockery::close();
});

it('lança exceção quando o jogador foi eliminado', function () {
    for ($i = 0; $i < BattlePlayer::INITIAL_LIVES; $i++) {
        $this->player->loseLife();
    }

    expect(fn () => $this->service->useTool($this->player, 'hint', '5x5'))
        ->toThrow(RuntimeException::class, 'Jogador eliminado');
});

it('lança exceção para ferramenta inexistente', function () {
    expect(fn () => $this->service->useTool($this->player, 'nuke', '5x5'))
        ->toThrow(InvalidArgumentException::class);
});

describe('hint', function () {
    it('consome a ferramenta e retorna a dica', function () {
        $result = $this->service->useTool($this->player, 'hint', '5x6');

        expect($result['tool'])->toBe('hint')
            ->and($result['hint'])->toBeString()->toContain('entre');
        expect($this->player->hasToolHint())->toBeFalse();
    });

    it('lança exceção ao tentar reusar a dica', function () {
        $this->service->useTool($this->player, 'hint', '5x6');

        expect(fn () => $this->service->useTool($this->player, 'hint', '5x6'))
            ->toThrow(RuntimeException::class, 'já foi usada');
    });
});

describe('eliminate', function () {
    it('retorna 2 opções e consome a ferramenta', function () {
        $result = $this->service->useTool($this->player, 'eliminate', '4x4');

        expect($result['tool'])->toBe('eliminate')
            ->and($result['reducedOptions']['options'])->toHaveCount(2)
            ->and($result['reducedOptions']['correctAnswer'])->toBe(16);
        expect($this->player->hasToolEliminate())->toBeFalse();
    });
});

describe('skip', function () {
    it('marca skipped e consome a ferramenta', function () {
        $result = $this->service->useTool($this->player, 'skip', '');

        expect($result)->toBe(['tool' => 'skip', 'skipped' => true]);
        expect($this->player->hasToolSkip())->toBeFalse();
    });
});
