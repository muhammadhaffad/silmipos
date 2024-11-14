<?php

namespace App\Filament\PointOfSale\Resources\InvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public function mount(): void
    {
        parent::mount();
        FilamentView::registerRenderHook(PanelsRenderHook::SCRIPTS_AFTER, function () {
            return <<<JS
                <script>
                    alert("HIT");
                </script>
            JS;
        });
    }
}
