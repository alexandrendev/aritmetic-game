<?php

namespace App\EventSubscriber;

use App\Entity\GameSession;
use App\Entity\GameSessionGuest;
use App\Event\GameAnswerReceivedEvent;
use App\Event\GameParticipantEliminatedEvent;
use App\Event\GameParticipantUpdatedEvent;
use App\Event\GameQuestionGeneratedEvent;
use App\Event\GameRoundFinishedEvent;
use App\Event\GameRoundStartedEvent;
use App\Event\GameSessionCreatedEvent;
use App\Event\GameSessionFinishedEvent;
use App\Event\GameSessionStartedEvent;
use App\Service\PusherPublisher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GameSessionPusherSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PusherPublisher $publisher
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            GameSessionCreatedEvent::class => 'onSessionCreated',
            GameSessionStartedEvent::class => 'onSessionStarted',
            GameQuestionGeneratedEvent::class => 'onQuestionGenerated',
            GameAnswerReceivedEvent::class => 'onAnswerReceived',
            GameParticipantUpdatedEvent::class => 'onParticipantUpdated',
            GameRoundFinishedEvent::class => 'onRoundFinished',
            GameRoundStartedEvent::class => 'onRoundStarted',
            GameParticipantEliminatedEvent::class => 'onParticipantEliminated',
            GameSessionFinishedEvent::class => 'onSessionFinished',
        ];
    }

    public function onSessionCreated(GameSessionCreatedEvent $event): void
    {
        $session = $event->getSession();
        $payload = [
            'sessionId' => $session->getId(),
            'session' => $this->serializeSession($session),
        ];

        if (null !== $session->getId()) {
            $this->publisher->publishToSession($session->getId(), 'game.session.created', $payload);
        }

        $userId = $session->getUserId();
        if (null !== $userId) {
            $this->publisher->publishToUser($userId, 'game.session.created', $payload);
        }
    }

    public function onSessionStarted(GameSessionStartedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $payload = [
            'sessionId' => $session->getId(),
            'session' => $this->serializeSession($session),
            'round' => $event->getRound(),
            'totalRounds' => $event->getTotalRounds(),
            'target' => $event->getTarget(),
            'question' => $event->getQuestion(),
        ];

        $this->publisher->publishToSession($session->getId(), 'game.session.started', $payload);

        $userId = $session->getUserId();
        if (null !== $userId) {
            $this->publisher->publishToUser($userId, 'game.session.started', $payload);
        }
    }

    public function onQuestionGenerated(GameQuestionGeneratedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $this->publisher->publishToSession($session->getId(), 'game.question.generated', [
            'sessionId' => $session->getId(),
            'question' => $event->getQuestion(),
        ]);
    }

    public function onAnswerReceived(GameAnswerReceivedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $this->publisher->publishToSession($session->getId(), 'game.answer.received', [
            'sessionId' => $session->getId(),
            'participant' => $this->serializeParticipant($event->getParticipant()),
            'answerResult' => [
                'correct' => $event->isCorrect(),
                'pointsEarned' => $event->getPointsEarned(),
                'submittedAnswer' => $event->getSubmittedAnswer(),
                'timeMs' => $event->getTimeMs(),
            ],
        ]);
    }

    public function onParticipantUpdated(GameParticipantUpdatedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $this->publisher->publishToSession($session->getId(), 'game.participant.updated', [
            'sessionId' => $session->getId(),
            'participant' => $this->serializeParticipant($event->getParticipant()),
        ]);
    }

    public function onRoundFinished(GameRoundFinishedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $this->publisher->publishToSession($session->getId(), 'game.round.finished', [
            'sessionId' => $session->getId(),
            'roundSummary' => $event->getSummary(),
        ]);
    }

    public function onRoundStarted(GameRoundStartedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $this->publisher->publishToSession($session->getId(), 'game.round.started', [
            'sessionId' => $session->getId(),
            'round' => $event->getRound(),
            'question' => $event->getQuestion(),
        ]);
    }

    public function onParticipantEliminated(GameParticipantEliminatedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $this->publisher->publishToSession($session->getId(), 'game.participant.eliminated', [
            'sessionId' => $session->getId(),
            'participant' => $this->serializeParticipant($event->getParticipant()),
        ]);
    }

    public function onSessionFinished(GameSessionFinishedEvent $event): void
    {
        $session = $event->getSession();
        if (null === $session->getId()) {
            return;
        }

        $payload = [
            'sessionId' => $session->getId(),
            'session' => $this->serializeSession($session),
            'reason' => $event->getReason(),
            'ranking' => $event->getRanking(),
        ];

        $this->publisher->publishToSession($session->getId(), 'game.session.finished', $payload);

        $userId = $session->getUserId();
        if (null !== $userId) {
            $this->publisher->publishToUser($userId, 'game.session.finished', $payload);
        }
    }

    private function serializeSession(GameSession $session): array
    {
        return [
            'id' => $session->getId(),
            'status' => $session->getStatus()?->value,
            'difficulty' => $session->getDifficulty()?->value,
            'userId' => $session->getUserId(),
            'state' => $session->getState(),
        ];
    }

    private function serializeParticipant(GameSessionGuest $participant): array
    {
        return [
            'id' => $participant->getId(),
            'guestId' => $participant->getGuest()?->getId(),
            'nickname' => $participant->getGuest()?->getNickName(),
            'score' => $participant->getScore(),
            'lives' => $participant->getLives(),
            'isAlive' => $participant->isAlive(),
        ];
    }
}
