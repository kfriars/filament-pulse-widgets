<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Filament\Pages\Dashboard as FilamentDashboard;
use Livewire\Attributes\Url;

/**
 * Filament's dashboard, with `?columns=` to narrow the Pulse widgets on it. The
 * browser tests and the manual pass before a release check the period selector
 * at 4 columns.
 */
class Dashboard extends FilamentDashboard
{
    #[Url]
    public int $columns = 2;

    public function getColumns(): int
    {
        return $this->columns;
    }
}
