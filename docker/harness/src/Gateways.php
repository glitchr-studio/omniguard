<?php

namespace Omnishield\Harness;

use Omnishield\GatewayFactoryInterface;
use Omnishield\Registry;
use Omnishield\Replay\InMemoryReplayStore;
use Omnishield\Testing\FixedGatewayFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The harness's registry, built by hand as any application outside a
 * framework builds it: a factory per gateway package installed, the options
 * from the environment (config/gateways.php), the providers called for real
 * (live) or answered from their packages' recorded answers.
 */
final class Gateways
{
    /** @var array<string, array{factory: string, needs: list<string>, options: array<string, mixed>, example: array<string, mixed>}> */
    public readonly array $config;

    /** @var array<string, GatewayFactoryInterface> */
    public readonly array $factories;

    /** @var array<string, bool> the gateways running on their example settings */
    public readonly array $examples;

    public readonly Registry $registry;
    public readonly HttpClientInterface $http;
    public readonly InMemoryReplayStore $replays;

    /**
     * @param bool $live     the providers themselves; their recorded answers otherwise
     * @param bool $examples whether a gateway whose settings are missing runs on its example ones
     */
    public function __construct(public readonly bool $live = false, bool $examples = true)
    {
        $this->config = require __DIR__.'/../config/gateways.php';
        $this->http = $live ? HttpClient::create(['headers' => ['User-Agent' => 'omnishield-harness']]) : new MockHttpClient(require __DIR__.'/../config/recorded.php');
        $this->replays = new InMemoryReplayStore();

        $factories = ['fixed' => new FixedGatewayFactory()];
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = match (true) {
                    str_ends_with($class, 'AltchaGatewayFactory') => new $class($this->replays),
                    str_ends_with($class, 'DisposableGatewayFactory') => new $class(),
                    default => new $class($this->http),
                };
                $factories[$factory->getName()] = $factory;
            }
        }
        $this->factories = $factories;

        $gateways = $used = [];
        foreach ($this->config as $name => $gateway) {
            if (!isset($factories[$gateway['factory']])) {
                continue;
            }
            if (!$this->missing($gateway)) {
                $gateways[$name] = ['factory' => $gateway['factory'], 'options' => array_filter($gateway['options'], static fn ($value) => null !== $value)];
                $used[$name] = false;
            } elseif ($examples) {
                $gateways[$name] = ['factory' => $gateway['factory'], 'options' => $gateway['example']];
                $used[$name] = true;
            }
        }
        $this->examples = $used;
        $this->registry = new Registry($factories, $gateways);
    }

    /**
     * @param array{needs: list<string>} $gateway
     *
     * @return list<string> the environment variables it still needs
     */
    public function missing(array $gateway): array
    {
        return array_values(array_filter($gateway['needs'], static fn (string $key) => false === getenv($key) || '' === getenv($key)));
    }
}
