<?php

namespace Omnishield\Testing;

use Omnishield\Config;
use Omnishield\GatewayFactory;
use Omnishield\GatewayInterface;

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
            'omnishield.factory_name' => 'fixed',
            'omnishield.factory_title' => 'Fixed',
            'omnishield.required_options' => [],
            'pass' => true,
        ]);
    }

    protected function build(Config $c): GatewayInterface
    {
        return new FixedGateway($c->bool('pass'));
    }
}
