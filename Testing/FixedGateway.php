<?php

namespace Omniguard\Testing;

use Omniguard\ChallengeInterface;
use Omniguard\ClassifierInterface;
use Omniguard\Model\Attempt;
use Omniguard\Model\Capabilities;
use Omniguard\Model\Classification;
use Omniguard\Model\Identity;
use Omniguard\Model\Label;
use Omniguard\Model\Reputation;
use Omniguard\Model\Submission;
use Omniguard\Model\Verdict;
use Omniguard\Model\Widget;
use Omniguard\ReputationInterface;

/**
 * A gateway that always says the same, for an application's own tests: it
 * passes every token and every submission, or refuses them all - and it
 * answers the three questions, so one configuration stands in for any
 * gateway (when@test: factory: fixed).
 *
 * Its widget is a hidden field already holding a token: a form submitted by
 * a test client carries it as a browser would. Only a token left empty is
 * refused when it passes - a test can still check that a form without one
 * is turned away.
 */
final class FixedGateway implements ChallengeInterface, ClassifierInterface, ReputationInterface
{
    public const FIELD = 'omniguard-token';
    public const TOKEN = 'omniguard-fixed-token';

    /** @var list<array{Submission, bool}> what report() was told, for the test to read */
    public array $reports = [];

    public function __construct(public readonly bool $pass = true)
    {
    }

    public static function passing(): self
    {
        return new self(true);
    }

    public static function failing(): self
    {
        return new self(false);
    }

    public function getName(): string
    {
        return 'fixed';
    }

    public function getTitle(): string
    {
        return $this->pass ? 'Fixed: passes everything' : 'Fixed: refuses everything';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(challenge: true, classifier: true, reputation: true, thirdParty: false, cookies: false, scores: true, actions: true, reports: true, reads: [Reputation::IP, Reputation::EMAIL, Reputation::NAME]);
    }

    public function widget(?string $action = null): Widget
    {
        return new Widget(self::FIELD, tag: 'input', attributes: ['type' => 'hidden', 'name' => self::FIELD, 'value' => self::TOKEN, 'data-omniguard-action' => $action ?? false], action: $action);
    }

    public function verify(Attempt $attempt): Verdict
    {
        if ($attempt->isEmpty()) {
            return Verdict::fail(Verdict::MISSING);
        }

        return $this->pass
            ? new Verdict(true, 1.0, $attempt->action, $attempt->hostname, new \DateTimeImmutable())
            : new Verdict(false, 0.0, $attempt->action, $attempt->hostname, new \DateTimeImmutable(), [Verdict::INVALID]);
    }

    public function classify(Submission $submission): Classification
    {
        return $this->pass ? Classification::ham() : new Classification(Label::SPAM, ['fixed']);
    }

    public function report(Submission $submission, bool $spam): void
    {
        $this->reports[] = [$submission, $spam];
    }

    public function lookup(Identity $identity): Reputation
    {
        if ($this->pass) {
            return Reputation::unknown();
        }
        $parts = array_keys(array_filter([Reputation::IP => $identity->ip, Reputation::EMAIL => $identity->email, Reputation::NAME => $identity->name], static fn (?string $v) => null !== $v && '' !== $v));

        return new Reputation(true, 1, 100.0, new \DateTimeImmutable(), $parts);
    }
}
