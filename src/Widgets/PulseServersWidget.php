<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

use Filament\Support\Components\Attributes\Unexposed;

class PulseServersWidget extends PulseWidget
{
    public string $card = 'pulse.servers';

    protected int|string|array $columnSpan = 'full';

    public int|string|null $ignoreAfter = null;

    public ?string $sortBy = null;

    /**
     * @var 'asc'|'desc'|null
     */
    public ?string $sortDirection = null;

    /**
     * @param  array<string, mixed>  $properties
     */
    #[Unexposed]
    public static function make(array $properties = []): PulseServersWidgetConfiguration
    {
        return app(PulseServersWidgetConfiguration::class, ['widget' => static::class, 'properties' => $properties]);
    }

    /**
     * The Servers card doesn't scroll, so it has no `expand`.
     */
    protected function getCardOptions(): array
    {
        return [
            'ignoreAfter' => $this->ignoreAfter,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ];
    }
}
