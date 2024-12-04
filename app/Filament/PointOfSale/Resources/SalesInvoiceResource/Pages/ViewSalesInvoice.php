<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Icetalker\FilamentTableRepeatableEntry;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
        ->schema([
            Section::make([
                Infolists\Components\TextEntry::make('transaksi_no')
                    ->label('No. Transaksi')
                    ->formatStateUsing(function ($state) {
                        return '#'.$state;
                    }),
                Infolists\Components\Grid::make(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('kontak.nama')
                            ->label('Pelanggan'),
                        Infolists\Components\TextEntry::make('nama_customer')
                            ->label('Nama pelanggan')
                    ]),
                Infolists\Components\Grid::make(3)
                    ->schema([
                        Infolists\Components\TextEntry::make('tanggal')
                            ->formatStateUsing(function ($state) {
                                \Carbon\Carbon::setLocale('id');
                                return \Carbon\Carbon::make($state)->isoFormat('dddd, D MMMM Y');
                            })
                            ->label('Tanggal')
                            ->columnStart([
                                'default' => 1,
                                'lg' => 3
                            ]),
                        Infolists\Components\TextEntry::make('tanggaltempo')
                            ->formatStateUsing(function ($state) {
                                \Carbon\Carbon::setLocale('id');
                                return \Carbon\Carbon::make($state)->isoFormat('dddd, D MMMM Y');
                            })
                            ->label('Tanggal')
                            ->columnStart([
                                'default' => 1,
                                'lg' => 3
                            ]),
                    ]),
            ])
        ]);
    }
}
