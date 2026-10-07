<?php

namespace Omniguard\Model;

/**
 * What the page shows for a captcha: a script, an element and its
 * attributes, the field the visitor's browser posts the token in - and,
 * before anything is loaded, who else the browser will talk to.
 *
 * html() prints it as it is; an application that renders its own markup
 * reads the parts.
 */
final readonly class Widget
{
    /**
     * @param string                    $field            the posted field that carries the token: "altcha", "cf-turnstile-response", "g-recaptcha-response"
     * @param string|null               $script           the provider's script: an address, absolute or on the site
     * @param array<string, string|bool> $scriptAttributes the script element's attributes beyond src: type, async, defer, integrity, crossorigin
     * @param string                    $tag              the element the script turns into a widget: "altcha-widget", "div"
     * @param array<string, string|bool> $attributes       its attributes: true prints the name alone, false leaves it out
     * @param string|null               $content          what goes inside the element, as HTML
     * @param string|null               $inline           a short script of this package's, after the element (reCAPTCHA's invisible and score modes)
     * @param string|null               $siteKey          the provider's public key for the site, when it has one
     * @param string|null               $action           the action it was made for
     * @param bool                      $thirdParty       whether the check itself goes through someone other than the site
     * @param bool                      $cookies          whether the provider sets or reads cookies in the visitor's browser
     * @param list<string>              $origins          who the visitor's browser talks to besides the site ("https://challenges.cloudflare.com"): name them first, then load
     */
    public function __construct(
        public string $field,
        public ?string $script = null,
        public array $scriptAttributes = [],
        public string $tag = 'div',
        public array $attributes = [],
        public ?string $content = null,
        public ?string $inline = null,
        public ?string $siteKey = null,
        public ?string $action = null,
        public bool $thirdParty = false,
        public bool $cookies = false,
        public array $origins = [],
    ) {
    }

    /** Whether the visitor's browser talks to anyone but the site: say so, and wait for consent, before html(). */
    public function reachesOthers(): bool
    {
        return $this->thirdParty || [] !== $this->origins;
    }

    /**
     * The widget's markup: the script, the element, the inline script.
     *
     * @param string|null $nonce  the page's Content-Security-Policy nonce, put on both scripts
     * @param bool        $script false leaves the provider's script out - already printed for another form of the page
     */
    public function html(?string $nonce = null, bool $script = true): string
    {
        $html = '';
        if ($script && null !== $this->script) {
            $html .= '<script'.self::attributes(['src' => $this->script] + $this->scriptAttributes + (null !== $nonce ? ['nonce' => $nonce] : [])).'></script>';
        }
        $html .= '<'.$this->tag.self::attributes($this->attributes).'>'.('input' === $this->tag ? '' : ($this->content ?? '').'</'.$this->tag.'>');
        if (null !== $this->inline) {
            $html .= '<script'.self::attributes(null !== $nonce ? ['nonce' => $nonce] : []).'>'.$this->inline.'</script>';
        }

        return $html;
    }

    /** @param array<string, string|bool|int|float|null> $attributes */
    private static function attributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if (false === $value || null === $value) {
                continue;
            }
            $html .= ' '.$name.(true === $value ? '' : '="'.htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"');
        }

        return $html;
    }
}
