<?php

namespace Omnishield\Tests;

use Omnishield\Model\Attempt;
use Omnishield\Model\Identity;
use Omnishield\Model\Submission;
use Omnishield\Model\Verdict;
use Omnishield\Testing\FixedGateway;
use Omnishield\Testing\FixedGatewayFactory;
use PHPUnit\Framework\TestCase;

final class FixedGatewayTest extends TestCase
{
    public function testItPassesEverythingButAnEmptyToken(): void
    {
        $gateway = (new FixedGatewayFactory())->create();
        $widget = $gateway->widget('contact');

        self::assertSame('<input type="hidden" name="omnishield-token" value="omnishield-fixed-token" data-omnishield-action="contact">', $widget->html());
        self::assertTrue($gateway->verify(Attempt::fromPost([FixedGateway::FIELD => FixedGateway::TOKEN], $widget))->passed);
        self::assertTrue($gateway->verify(Attempt::fromPost([FixedGateway::FIELD => FixedGateway::TOKEN], $widget))->passed, 'and again: it remembers nothing');
        self::assertTrue($gateway->verify(new Attempt(''))->failedFor(Verdict::MISSING));
        self::assertFalse($gateway->classify(new Submission('Buy now'))->isSpam());
        self::assertFalse($gateway->lookup(new Identity('192.0.2.1'))->known);
        self::assertSame(['challenge', 'classifier', 'reputation'], $gateway->capabilities()->questions());
    }

    public function testItRefusesEverything(): void
    {
        $gateway = (new FixedGatewayFactory())->create(['pass' => 'false']);

        self::assertTrue($gateway->verify(new Attempt(FixedGateway::TOKEN))->failedFor(Verdict::INVALID));
        self::assertTrue($gateway->classify(new Submission('Hello'))->isSpam());
        self::assertSame(['ip', 'email'], $gateway->lookup(new Identity('192.0.2.1', 'a@example.org'))->reasons);

        $gateway->report($submission = new Submission('Hello'), false);
        self::assertSame([[$submission, false]], $gateway->reports);
    }
}
