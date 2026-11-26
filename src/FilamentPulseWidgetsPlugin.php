<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Kfriars\FilamentPulseWidgets\Widgets\PulseWidget;
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

    /**
     * Who can see Pulse widgets on this panel. Unset leaves it to Pulse's `viewPulse` gate.
     *
     * @var bool | (Closure(?Authenticatable): bool) | null
     */
    protected bool|Closure|null $rule = null;

    /**
     * Who can see Pulse widgets of each class, in place of the panel-wide rule.
     *
     * @var array<class-string<PulseWidget>, bool | (Closure(?Authenticatable): bool)>
     */
    protected array $widgetRules = [];

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

    /**
     * Who can see Pulse widgets on this panel, in place of Pulse's `viewPulse` gate.
     * A closure is given the panel's signed-in user.
     *
     * With `widgets`, the rule is only for exactly those Pulse widget classes (not
     * their subclasses), and takes precedence over the panel-wide rule.
     *
     * @param  bool | (Closure(?Authenticatable): bool)  $condition
     * @param  array<class-string<PulseWidget>> | null  $widgets
     */
    public function authorize(bool|Closure $condition, ?array $widgets = null): static
    {
        if ($widgets === null) {
            $this->rule = $condition;

            return $this;
        }

        foreach ($widgets as $pulseWidget) {
            $this->widgetRules[$pulseWidget] = $condition;
        }

        return $this;
    }

    /**
     * Whether the panel's signed-in user can see a Pulse widget of the given class.
     *
     * @param  class-string<PulseWidget>  $pulseWidget
     */
    public function canView(string $pulseWidget): bool
    {
        $rule = $this->widgetRules[$pulseWidget] ?? $this->rule;
        $user = Filament::auth()->user();

        if ($rule === null) {
            return Gate::forUser($user)->allows('viewPulse');
        }

        return $rule instanceof Closure ? $rule($user) : $rule;
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
