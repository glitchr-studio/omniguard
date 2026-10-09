<?php

namespace Omnishield;

use Omnishield\Model\Attempt;
use Omnishield\Model\Verdict;
use Omnishield\Model\Widget;

/**
 * Is this token valid? A captcha: the page shows a widget, the visitor's
 * browser earns a token and posts it with the form, the site asks the
 * gateway about it.
 */
interface ChallengeInterface extends GatewayInterface
{
    /**
     * What the page shows: a script, an element and its attributes, the
     * field the token is posted in.
     *
     * @param string|null $action what the form is for ("contact", "signup"):
     *                            the provider signs it into the token where it
     *                            can, and verify() checks it back
     */
    public function widget(?string $action = null): Widget;

    /**
     * Whether the token holds - signed, unexpired, unused, for this action
     * and this host when the attempt names them. A refusal is a Verdict that
     * did not pass, never an exception.
     *
     * @throws Exception\UnreachableException when the provider did not answer
     * @throws Exception\InvalidKeyException   when it refused the site's secret
     */
    public function verify(Attempt $attempt): Verdict;
}
