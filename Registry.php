<?php

namespace Omniguard;

use Omniguard\Exception\InvalidConfigException;
use Omniguard\Exception\NotSupportedException;

/**
 * The application's gateways by name, each built once from its factory and
 * options:
 *
 *   new Registry([new AltchaGatewayFactory($replays), new AkismetGatewayFactory($http), new DisposableGatewayFactory()], [
 *       'forms' => ['factory' => 'altcha', 'options' => ['hmac_key' => '...']],
 *       'comments' => ['factory' => 'akismet', 'options' => ['api_key' => '...', 'site' => 'https://example.org']],
 *       'emails' => ['factory' => 'disposable'],
 *   ]);
 *
 *   $registry->challenge('forms')->verify($attempt);
 */
final class Registry
{
    /** @var array<string, GatewayFactoryInterface> */
    private array $factories = [];

    /** @var array<string, GatewayInterface> */
    private array $gateways = [];

    /**
     * @param iterable<GatewayFactoryInterface>                                      $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): GatewayInterface
    {
        return $this->gateways[$name] ??= $this->factory($name)->create($this->config[$name]['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** The gateway, as a captcha. */
    public function challenge(string $name): ChallengeInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof ChallengeInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'is this token valid');
    }

    /** The gateway, as a spam classifier. */
    public function classifier(string $name): ClassifierInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof ClassifierInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'is this content spam');
    }

    /** The gateway, as a list of known abusers. */
    public function reputation(string $name): ReputationInterface
    {
        $gateway = $this->get($name);

        return $gateway instanceof ReputationInterface ? $gateway : throw NotSupportedException::question($gateway, $name, 'is this identity known for abuse');
    }

    /** @return array<string, mixed> the options a gateway is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /**
     * A gateway built afresh, its configured options with $overrides over
     * them - a key typed in a back office, say. Not kept: get() still gives
     * the configured one.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): GatewayInterface
    {
        return $this->factory($name)->create(array_replace($this->config[$name]['options'] ?? [], $overrides));
    }

    /** @return list<string> the configured gateways' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return array<string, GatewayInterface> */
    public function all(): array
    {
        $all = [];
        foreach (array_keys($this->config) as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** @return string[] the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
    }

    private function factory(string $name): GatewayFactoryInterface
    {
        $gateway = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" gateway; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$gateway['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" gateway; installed: %s.', $gateway['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }
}
