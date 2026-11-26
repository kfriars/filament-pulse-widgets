<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Widgets;

use Filament\Support\Components\Attributes\Unexposed;

class PulseUsageWidget extends PulseWidget
{
    public string $card = 'pulse.usage';

    /**
     * @var 'requests'|'slow_requests'|'jobs'|null
     */
    public ?string $type = null;

    /**
     * @param  array<string, mixed>  $properties
     */
    #[Unexposed]
    public static function make(array $properties = []): PulseUsageWidgetConfiguration
    {
        return app(PulseUsageWidgetConfiguration::class, ['widget' => static::class, 'properties' => $properties]);
    }

    protected function getCardOptions(): array
    {
        return [
            ...parent::getCardOptions(),
            'type' => $this->type,
        ];
    }
}
