<?php

declare(strict_types=1);

namespace Kfriars\FilamentPulseWidgets\Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    /**
     * Pest's test classes hide the WithWorkbench trait from Testbench's
     * detection, so package discovery (Filament, Livewire, Pulse) is turned on here.
     */
    protected $enablesPackageDiscoveries = true;

    /**
     * Boot the app afresh, as its next request would, for packages to find files
     * a test has published where they look only while booting.
     */
    public function rebootApplication(): void
    {
        $this->reloadApplication();
    }

    protected function defineEnvironment($app): void
    {
        // testbench.yaml's env (and its APP_KEY) only applies to the served workbench.
        $app['config']->set('app.key', 'base64:'.base64_encode(Encrypter::generateKey('aes-256-cbc')));
    }
}
