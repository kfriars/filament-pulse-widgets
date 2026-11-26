<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

class PulseSlowQueriesWidgetConfiguration extends PulseWidgetConfiguration
{
    /**
     * Show slow queries as plain text, without SQL syntax highlighting.
     */
    public function withoutHighlighting(bool $condition = true): static
    {
        $this->properties['withoutHighlighting'] = $condition;

        return $this;
    }
}
