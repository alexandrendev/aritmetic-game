<?php

use App\Entity\BattlePlayer;

beforeEach(function () {
    $this->player = new BattlePlayer();
});

describe('estado inicial', function () {
    it('inicia com vidas iniciais, score zero e status waiting', function () {
        expect($this->player->getLives())->toBe(BattlePlayer::INITIAL_LIVES)
            ->and($this->player->getScore())->toBe(0)
            ->and($this->player->getStatus())->toBe(BattlePlayer::STATUS_WAITING)
            ->and($this->player->isAlive())->toBeTrue();
    });

    it('inicia com todas as ferramentas disponíveis', function () {
        expect($this->player->hasToolHint())->toBeTrue()
            ->and($this->player->hasToolEliminate())->toBeTrue()
            ->and($this->player->hasToolSkip())->toBeTrue()
            ->and($this->player->getToolsUsedCount())->toBe(0);
    });
});

describe('loseLife', function () {
    it('decrementa as vidas mas não fica negativo', function () {
        $this->player->loseLife();
        expect($this->player->getLives())->toBe(BattlePlayer::INITIAL_LIVES - 1);
    });

    it('marca como ghost quando as vidas chegam a zero', function () {
        for ($i = 0; $i < BattlePlayer::INITIAL_LIVES; $i++) {
            $this->player->loseLife();
        }

        expect($this->player->getLives())->toBe(0)
            ->and($this->player->getStatus())->toBe(BattlePlayer::STATUS_GHOST)
            ->and($this->player->isAlive())->toBeFalse();
    });

    it('mantém vidas em zero mesmo se loseLife for chamado a mais', function () {
        for ($i = 0; $i < BattlePlayer::INITIAL_LIVES + 5; $i++) {
            $this->player->loseLife();
        }

        expect($this->player->getLives())->toBe(0);
    });
});

describe('addScore', function () {
    it('soma pontos cumulativamente', function () {
        $this->player->addScore(10)->addScore(5)->addScore(3);

        expect($this->player->getScore())->toBe(18);
    });
});

describe('contagem de ferramentas usadas', function () {
    it('conta uma ferramenta usada', function () {
        $this->player->useToolHint();

        expect($this->player->hasToolHint())->toBeFalse()
            ->and($this->player->getToolsUsedCount())->toBe(1);
    });

    it('conta as três ferramentas após o uso de todas', function () {
        $this->player->useToolHint();
        $this->player->useToolEliminate();
        $this->player->useToolSkip();

        expect($this->player->getToolsUsedCount())->toBe(3)
            ->and($this->player->hasToolHint())->toBeFalse()
            ->and($this->player->hasToolEliminate())->toBeFalse()
            ->and($this->player->hasToolSkip())->toBeFalse();
    });
});
