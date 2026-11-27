<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

use Filament\Support\Components\Attributes\Unexposed;

class PulseSlowQueriesWidget extends PulseWidget
{
    public string $card = 'pulse.slow-queries';

    protected int|string|array $columnSpan = 'full';

    /**
     * @internal
     */
    public ?bool $withoutHighlighting = null;

    /**
     * @param  array<string, mixed>  $properties
     */
    #[Unexposed]
    public static function make(array $properties = []): PulseSlowQueriesWidgetConfiguration
    {
        return app(PulseSlowQueriesWidgetConfiguration::class, ['widget' => static::class, 'properties' => $properties]);
    }

    /**
     * @internal
     */
    protected function getCardOptions(): array
    {
        return [
            ...parent::getCardOptions(),
            'withoutHighlighting' => $this->withoutHighlighting,
        ];
    }
}
