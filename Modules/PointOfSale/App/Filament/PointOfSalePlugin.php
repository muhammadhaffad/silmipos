<?php

namespace Modules\PointOfSale\App\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class PointOfSalePlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'PointOfSale';
    }

    public function getId(): string
    {
        return 'pointofsale';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
