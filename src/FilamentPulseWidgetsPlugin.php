<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Filament\Contracts\Plugin;
use Filament\Panel;
use LogicException;

/**
 * Required on every panel that shows Pulse widgets. It places none itself:
 * a developer places Pulse widgets wherever a widget goes.
 *
 * @phpstan-consistent-constructor
 */
class FilamentPulseWidgetsPlugin implements Plugin
{
    public const ID = 'filament-pulse-widgets';

    public static function make(): static
    {
        return new static;
    }

    /**
     * The plugin on the current panel. Throws Filament's own error when the panel doesn't have it.
     */
    public static function get(): static
    {
        $plugin = filament(self::ID);

        if (! $plugin instanceof static) {
            throw new LogicException('Plugin ['.self::ID.'] on the current panel is not a ['.static::class.'].');
        }

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
