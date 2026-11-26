<?php

namespace Kfriars\FilamentPulseWidgets\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    protected $enablesPackageDiscoveries = true;
}
