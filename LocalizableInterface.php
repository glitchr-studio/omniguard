<?php

namespace Omniguard;

/**
 * A captcha whose widget shows texts the site can give it - in the
 * visitor's language, or in words of its own ("I'm not a robot",
 * "Verifying...", "Verified").
 *
 *     $gateway instanceof LocalizableInterface
 *         && $gateway = $gateway->localized('fr', ['label' => 'Je ne suis pas un robot']);
 *
 * The Symfony bridge does it for every widget it prints: the gateway's own
 * language if it was given one, else the request's; the texts of its
 * translation domain "omniguard" (<gateway>.<text>) in that language; the
 * gateway's own `strings` option over them.
 */
interface LocalizableInterface extends ChallengeInterface
{
    /** @return list<string> the texts its widget shows, by the name the widget gives them */
    public function texts(): array;

    /** The language the gateway itself was given (its `language` option), null to follow the visitor's */
    public function language(): ?string;

    /**
     * The same gateway, its widget in that language with these texts - any
     * left out are the widget's own for that language, else its English.
     * What the gateway itself was given wins: its language over this one,
     * its texts (its `strings` option) over these.
     *
     * @param string                $language a language tag: "fr", "de", "ja", "pt-br"
     * @param array<string, string> $strings  the texts, by name
     */
    public function localized(string $language, array $strings = []): static;
}
