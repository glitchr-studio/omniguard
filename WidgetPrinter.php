<?php

namespace Omnishield;

use Omnishield\Model\Widget;

/**
 * Prints widgets for one page: a provider's script once, however many forms
 * the page holds. One printer per response - reset() between two, in a
 * worker that serves many.
 */
final class WidgetPrinter
{
    /** @var array<string, true> the scripts already printed */
    private array $printed = [];

    public function print(Widget $widget, ?string $nonce = null): string
    {
        $first = null === $widget->script || !isset($this->printed[$widget->script]);
        if (null !== $widget->script) {
            $this->printed[$widget->script] = true;
        }

        return $widget->html($nonce, $first);
    }

    public function reset(): void
    {
        $this->printed = [];
    }
}
