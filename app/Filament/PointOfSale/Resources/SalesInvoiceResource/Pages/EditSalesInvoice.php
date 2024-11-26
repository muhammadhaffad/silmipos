<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
