<?php

namespace Omniguard;

/**
 * A captcha whose challenge the site itself issues - ALTCHA: the page asks
 * the site for a challenge, solves it, posts the solution. Nobody else is
 * involved, so somebody on the site's side has to serve it: a route (the
 * Symfony bridge has one), or the widget, which carries one inline when no
 * address is configured.
 */
interface ChallengeIssuerInterface extends ChallengeInterface
{
    /**
     * A fresh challenge, as the widget fetches it (JSON). Never cache it: one
     * challenge, one solution, one form.
     *
     * @return array<string, mixed>
     */
    public function issue(?string $action = null): array;
}
