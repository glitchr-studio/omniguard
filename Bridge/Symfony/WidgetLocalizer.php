<?php

namespace Omniguard\Bridge\Symfony;

use Omniguard\ChallengeInterface;
use Omniguard\LocalizableInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A captcha's widget in the visitor's language: for a gateway that takes
 * texts (LocalizableInterface), the request's locale - or the language the
 * gateway was given - and the texts of the
 * bridge's translation domain "omniguard" - "<gateway>.<text>", French,
 * English, German and Japanese shipped, an application's own catalogue
 * over them -, the gateway's own `strings` option winning over both. A text
 * the catalogue does not hold is left to the widget.
 */
final class WidgetLocalizer
{
    public const DOMAIN = 'omniguard';

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?RequestStack $requests = null,
    ) {
    }

    public function localize(ChallengeInterface $gateway): ChallengeInterface
    {
        if (!$gateway instanceof LocalizableInterface || null === $this->translator) {
            return $gateway;
        }
        $locale = $gateway->language()
            ?? $this->requests?->getCurrentRequest()?->getLocale()
            ?? (method_exists($this->translator, 'getLocale') ? $this->translator->getLocale() : null);
        if (null === $locale || '' === $locale) {
            return $gateway;
        }

        $strings = [];
        foreach ($gateway->texts() as $text) {
            $id = $gateway->getName().'.'.$text;
            $translated = $this->translator->trans($id, [], self::DOMAIN, $locale);
            if ($translated !== $id && '' !== $translated) {
                $strings[$text] = $translated;
            }
        }

        return $gateway->localized($locale, $strings);
    }
}
