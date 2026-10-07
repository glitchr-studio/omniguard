<?php

namespace Omniguard\Tests;

use Omniguard\ChallengeIssuerInterface;
use Omniguard\Config;
use Omniguard\Exception\UnreachableException;
use Omniguard\GatewayFactory;
use Omniguard\GatewayInterface;
use Omniguard\Model\Attempt;
use Omniguard\Model\Capabilities;
use Omniguard\Model\Verdict;
use Omniguard\Model\Widget;
use Omniguard\Replay\InMemoryReplayStore;
use Omniguard\Replay\ReplayStoreInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A captcha kept in memory, for the tests: it issues "good-<n>" tokens,
 * passes each once (through the replay store it is given), says "duplicate"
 * the second time, "invalid" to anything else, and does not answer to
 * "down". Its factory asks for what a real one does: a store, an HTTP
 * client.
 */
final class StubFactory extends GatewayFactory
{
    public function __construct(public readonly ?ReplayStoreInterface $replays = null, public readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omniguard.factory_name' => 'stub',
            'omniguard.factory_title' => 'Stub',
            'omniguard.required_options' => ['secret'],
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new StubGateway($this->replays ?? new InMemoryReplayStore(), (string) $c['secret'], $this->http);
    }
}

final class StubGateway implements ChallengeIssuerInterface
{
    private int $issued = 0;

    public function __construct(public readonly ReplayStoreInterface $replays, public readonly string $secret, public readonly ?HttpClientInterface $http)
    {
    }

    public function getName(): string
    {
        return 'stub';
    }

    public function getTitle(): string
    {
        return 'Stub';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(challenge: true, thirdParty: false, actions: true);
    }

    public function widget(?string $action = null): Widget
    {
        return new Widget('stub-token', '/stub.js', ['type' => 'module'], 'stub-widget', ['data-action' => $action ?? false], action: $action);
    }

    public function issue(?string $action = null): array
    {
        return ['token' => 'good-'.++$this->issued, 'action' => $action];
    }

    public function verify(Attempt $attempt): Verdict
    {
        if ($attempt->isEmpty()) {
            return Verdict::fail(Verdict::MISSING);
        }
        if ('down' === $attempt->token) {
            throw new UnreachableException('stub', 'No answer.');
        }
        if (!str_starts_with($attempt->token, 'good-')) {
            return Verdict::fail(Verdict::INVALID);
        }
        if (!$this->replays->spend($attempt->token, new \DateTimeImmutable('+5 minutes'))) {
            return Verdict::fail(Verdict::DUPLICATE);
        }

        return new Verdict(true, action: $attempt->action, hostname: $attempt->hostname, at: new \DateTimeImmutable());
    }
}
