<?php

namespace App\Filament\PointOfSale\Resources\InvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;
}
