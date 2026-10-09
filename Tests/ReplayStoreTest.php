<?php

namespace Omnishield\Tests;

use Omnishield\Replay\CacheReplayStore;
use Omnishield\Replay\InMemoryReplayStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ReplayStoreTest extends TestCase
{
    public function testATokenIsSpentOnceAndForgottenWhenItWouldHaveLapsed(): void
    {
        $now = 1_000_000;
        $store = new InMemoryReplayStore(static function () use (&$now): int { return $now; });

        self::assertTrue($store->spend('t1', new \DateTimeImmutable('@'.($now + 60))));
        self::assertFalse($store->spend('t1', new \DateTimeImmutable('@'.($now + 60))), 'the second time');
        self::assertTrue($store->spend('t2', new \DateTimeImmutable('@'.($now + 600))));

        $now += 61;
        self::assertTrue($store->spend('t3', new \DateTimeImmutable('@'.($now + 60))));
        self::assertSame(2, $store->count(), 't1 lapsed: forgotten');
        self::assertFalse($store->spend('t2', new \DateTimeImmutable('@'.($now + 60))));
    }

    public function testAPsr6PoolIsSharedByWhoeverUsesIt(): void
    {
        if (!class_exists(ArrayAdapter::class)) {
            self::markTestSkipped('symfony/cache is not installed.');
        }
        $pool = new ArrayAdapter();
        $until = new \DateTimeImmutable('+10 minutes');

        self::assertTrue((new CacheReplayStore($pool))->spend('a token {with} reserved:characters/', $until));
        self::assertFalse((new CacheReplayStore($pool))->spend('a token {with} reserved:characters/', $until), 'another request, the same pool');
        self::assertTrue((new CacheReplayStore($pool))->spend('another token', $until));
        self::assertTrue((new CacheReplayStore($pool, 'other.'))->spend('another token', $until), 'another prefix, another store');
    }
}
