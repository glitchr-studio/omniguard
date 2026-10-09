<?php

namespace Omnishield\Bridge\Twig;

use Omnishield\Model\Widget;
use Omnishield\Bridge\Symfony\WidgetLocalizer;
use Omnishield\Registry;
use Omnishield\WidgetPrinter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * A captcha's widget in a template, for a form that is not a Symfony form:
 *
 *     <form method="post">
 *         ...
 *         {{ omnishield_widget() }}                         {# the default captcha #}
 *         {{ omnishield_widget('forms', 'contact') }}       {# a captcha, an action #}
 *         {{ omnishield_widget('forms', nonce: csp_nonce) }}
 *     </form>
 *
 *     {% set widget = omnishield_widget_data('forms') %}   {# its parts: widget.field, widget.origins... #}
 *
 * A provider's script is printed once per page, however many widgets.
 */
final class OmnishieldExtension extends AbstractExtension
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
            new TwigFunction('omnishield_widget', $this->widget(...), ['is_safe' => ['html']]),
            new TwigFunction('omnishield_widget_data', $this->data(...)),
        ];
    }

    public function widget(?string $gateway = null, ?string $action = null, ?string $nonce = null): string
    {
        return $this->printer->print($this->data($gateway, $action), $nonce);
    }

    public function data(?string $gateway = null, ?string $action = null): Widget
    {
        $gateway ??= $this->gateway ?? throw new \LogicException('No captcha named: pass its name, or set omnishield.challenge.gateway.');

        $challenge = $this->registry->challenge($gateway);

        return ($this->localizer?->localize($challenge) ?? $challenge)->widget($action);
    }
}
