<?php

namespace Omnishield\Replay;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Spent tokens in a PSR-6 cache pool (psr/cache, not required by this
 * package): shared between the requests of every process that uses the
 * pool - a filesystem pool on one server, Redis or Memcached on several.
 *
 * PSR-6 has no "add if absent": between this request's read and its write,
 * another may read the same token as unspent. The window is a few
 * microseconds, for a token solved once; where that matters, implement
 * ReplayStoreInterface on an atomic store (a unique key, SET NX).
 */
final class CacheReplayStore implements ReplayStoreInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly string $prefix = 'omnishield.spent.',
    ) {
    }

    public function spend(string $id, \DateTimeImmutable $until): bool
    {
        // PSR-6 keys: no {}()/\@: and a short length - a hash is both.
        $item = $this->pool->getItem($this->prefix.hash('sha256', $id));
        if ($item->isHit()) {
            return false;
        }
        $item->set(true);
        $item->expiresAt($until > new \DateTimeImmutable() ? $until : new \DateTimeImmutable('+1 second'));

        return $this->pool->save($item);
    }
}
