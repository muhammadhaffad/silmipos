<?php

namespace App\Filament\PointOfSale\Resources\InvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    public function getTabs(): array
    {
        return [
            null => Tab::make('All')
        ];
    }
}
