<?php

namespace Omnishield\Replay;

/**
 * Where spent tokens are remembered, until they would have lapsed anyway: a
 * token serves once. A provider that checks tokens on its servers (Turnstile,
 * reCAPTCHA) refuses a second use itself; a challenge the site issues and
 * checks alone (ALTCHA) cannot know it saw a solution before - this store
 * does.
 *
 * spend() must be atomic where requests run side by side: two requests
 * spending the same token at the same moment, only one may be told "first".
 * A database's unique key, Redis's SET NX, a lock around the check do it.
 */
interface ReplayStoreInterface
{
    /**
     * Marks the token spent until $until.
     *
     * @param string $id an identifier of the token: hash it if it is long or secret
     *
     * @return bool true the first time, false when it was spent already
     */
    public function spend(string $id, \DateTimeImmutable $until): bool;
}
