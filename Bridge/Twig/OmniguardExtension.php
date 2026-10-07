<?php

namespace Omniguard\Bridge\Twig;

use Omniguard\Model\Widget;
use Omniguard\Bridge\Symfony\WidgetLocalizer;
use Omniguard\Registry;
use Omniguard\WidgetPrinter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A captcha's widget in a template, for a form that is not a Symfony form:
 *
 *     <form method="post">
 *         ...
 *         {{ omniguard_widget() }}                         {# the default captcha #}
 *         {{ omniguard_widget('forms', 'contact') }}       {# a captcha, an action #}
 *         {{ omniguard_widget('forms', nonce: csp_nonce) }}
 *     </form>
 *
 *     {% set widget = omniguard_widget_data('forms') %}   {# its parts: widget.field, widget.origins... #}
 *
 * A provider's script is printed once per page, however many widgets.
 */
final class OmniguardExtension extends AbstractExtension
{
    public function __construct(
        private readonly Registry $registry,
        private readonly WidgetPrinter $printer = new WidgetPrinter(),
        private readonly ?string $gateway = null,
        private readonly ?WidgetLocalizer $localizer = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('omniguard_widget', $this->widget(...), ['is_safe' => ['html']]),
            new TwigFunction('omniguard_widget_data', $this->data(...)),
        ];
    }

    public function widget(?string $gateway = null, ?string $action = null, ?string $nonce = null): string
    {
        return $this->printer->print($this->data($gateway, $action), $nonce);
    }

    public function data(?string $gateway = null, ?string $action = null): Widget
    {
        $gateway ??= $this->gateway ?? throw new \LogicException('No captcha named: pass its name, or set omniguard.challenge.gateway.');

        $challenge = $this->registry->challenge($gateway);

        return ($this->localizer?->localize($challenge) ?? $challenge)->widget($action);
    }
}
