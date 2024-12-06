<?php

namespace App\Filament\PointOfSale\Resources\SalesReturnResource\Pages;

use App\Filament\PointOfSale\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesReturn extends ListRecords
{
    protected static string $resource = SalesReturnResource::class;

    public function getBreadcrumb(): ?string
    {
        return 'Daftar';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Retur Penjualan')
                ->icon('heroicon-m-plus'),
        ];
    }
}
