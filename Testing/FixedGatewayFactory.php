<?php

namespace Omniguard\Testing;

use Omniguard\Config;
use Omniguard\GatewayFactory;
use Omniguard\GatewayInterface;

/**
 * The fixed gateway, by configuration - for an application's test
 * environment:
 *
 *   options:
 *     pass: true                 # false: every token, submission and identity is refused
 */
final class FixedGatewayFactory extends GatewayFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omniguard.factory_name' => 'fixed',
            'omniguard.factory_title' => 'Fixed',
            'omniguard.required_options' => [],
            'pass' => true,
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new FixedGateway($c->bool('pass'));
    }
}
