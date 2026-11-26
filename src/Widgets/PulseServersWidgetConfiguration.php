<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

class PulseServersWidgetConfiguration extends PulseWidgetConfiguration
{
    /**
     * Hide servers that haven't reported for this long: seconds, or a string like "3 hours".
     */
    public function ignoreAfter(int|string $ignoreAfter): static
    {
        $this->properties['ignoreAfter'] = $ignoreAfter;

        return $this;
    }

    /**
     * Sort servers by one of the Servers card's values, such as `name` or `cpu_current`.
     */
    public function sortBy(string $sortBy): static
    {
        $this->properties['sortBy'] = $sortBy;

        return $this;
    }

    /**
     * @param  'asc'|'desc'  $sortDirection
     */
    public function sortDirection(string $sortDirection): static
    {
        $this->properties['sortDirection'] = $sortDirection;

        return $this;
    }
}
