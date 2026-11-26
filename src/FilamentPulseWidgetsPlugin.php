<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets;

use Closure;
use Composer\InstalledVersions;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
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

    /**
     * Filament registers a panel's assets when the panel boots, so they load only
     * on panels with this plugin.
     *
     * Pulse cards need Pulse's own JavaScript (Chart.js and `formatDate`), and
     * `Pulse::js()` would load Livewire a second time. So the installed Pulse's
     * `dist/pulse.js` is registered under Pulse's package name, which makes its
     * `?v=` follow Pulse's version.
     *
     * It's a module because it throws if it runs twice in one page, and wherever
     * requests are served from the console, Filament includes a panel's assets
     * twice. A browser runs a module once however often a page includes it, and
     * before Livewire starts. It only sets `window.Chart` and `window.formatDate`.
     */
    public function register(Panel $panel): void
    {
        $panel
            ->assets([
                Css::make('filament-pulse-widgets', __DIR__.'/../resources/dist/filament-pulse-widgets.css'),
            ], 'kfriars/filament-pulse-widgets')
            ->assets([
                Js::make('pulse', InstalledVersions::getInstallPath('laravel/pulse').'/dist/pulse.js')->module(),
            ], 'laravel/pulse');
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
