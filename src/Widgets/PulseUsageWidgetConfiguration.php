<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

class PulseUsageWidgetConfiguration extends PulseWidgetConfiguration
{
    /**
     * Show only one type of usage. Unset, the Usage card shows its own dropdown.
     *
     * @param  'requests'|'slow_requests'|'jobs'  $type
     */
    public function type(string $type): static
    {
        $this->properties['type'] = $type;

        return $this;
    }
}
