<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use App\Models\PenjualanDetail;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Icetalker\FilamentTableRepeatableEntry;
use Icetalker\FilamentTableRepeatableEntry\Infolists\Components\TableRepeatableEntry;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;
    protected static ?string $title = 'Detail Invoice Penjualan';

    public function mount(int|string $record): void
    {
        $this->record = app(static::getModel())
        ->resolveRouteBindingQuery(static::getResource()::getEloquentQuery()->with(['penjualanDetail.produkVarian', 'gudang']), $record, static::getResource()::getRecordRouteKeyName())
        ->first();
        if ($this->record === null) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel($this->getModel(), [$record]);
        }
        $this->authorizeAccess();

        if (! $this->hasInfolist()) {
            $this->fillForm();
        }
    }

    public function getBreadcrumb(): string
    {
        return 'Detail';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Kembali')
                ->color('gray')
                ->icon('heroicon-s-arrow-left')
                ->url(url()->route('filament.pos.resources.sales-invoices.index')),
            Actions\Action::make('Ubah Penjualan')
                ->color('primary')
                ->icon('heroicon-s-pencil-square')
                ->url(url()->route('filament.pos.resources.sales-invoices.edit', ['record' => $this->record->id_penjualan])),
        ];
    }

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
                Infolists\Components\TextEntry::make('catatan')
                    ->default('-'),
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
                        TableRepeatableEntry::make('penjualanDetail')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('produkVarian.varian'),
                                Infolists\Components\TextEntry::make('gudang.nama'),
                                Infolists\Components\TextEntry::make('qty')
                                    ->formatStateUsing(function ($state){
                                        return number($state);
                                    }),
                                Infolists\Components\TextEntry::make('harga')
                                    ->formatStateUsing(function ($state){
                                        return 'Rp' . number_format($state, 0, ',', '.');
                                    }),
                                Infolists\Components\TextEntry::make('diskon')
                                    ->formatStateUsing(function ($state){
                                        return number_format($state, 2, ',', '.') . '%';
                                    }),
                                Infolists\Components\TextEntry::make('total')
                                    ->formatStateUsing(function ($state){
                                        return 'Rp' . number_format($state, 0, ',', '.');
                                    }),
                            ])
                            ->extraAttributes([
                                'class' => '
                                [&_.filament-table-repeatable_td:nth-child(1)>*]:md:!w-[220px] 
                                [&_.filament-table-repeatable_td:nth-child(2)>*]:md:!w-[100px] 
                                [&_.filament-table-repeatable_td:nth-child(3)>*]:md:!w-[100px] 
                                [&_.filament-table-repeatable_td:nth-child(4)>*]:md:!w-[150px] 
                                [&_.filament-table-repeatable_td:nth-child(5)>*]:md:!w-[100px] 
                                [&_.filament-table-repeatable_td:nth-child(6)>*]:md:!w-[150px] 
                                [&_.filament-table-repeatable_td:nth-child(7)>*]:md:!w-[40px]'
                            ])
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('totalraw')
                            ->formatStateUsing(function ($state){
                                return 'Rp' . number_format($state, 0, ',', '.');
                            })
                            ->label('Subtotal')
                            ->columnStart([
                                'default' => 1,
                                'lg' => 3
                            ])
                            ->extraAttributes([
                                'class' => '[&>div>div>div]:justify-end'
                            ])
                            ->inlineLabel(),
                        Infolists\Components\TextEntry::make('diskon')
                            ->formatStateUsing(function ($state){
                                return number_format($state, 2, ',', '.') . '%';
                            })
                            ->label('diskon')
                            ->columnStart([
                                'default' => 1,
                                'lg' => 3
                            ])
                            ->extraAttributes([
                                'class' => '[&>div>div>div]:justify-end'
                            ])
                            ->inlineLabel(),
                        Infolists\Components\TextEntry::make('grandtotal')
                            ->formatStateUsing(function ($state){
                                return 'Rp' . number_format($state, 0, ',', '.');
                            })
                            ->label('Grandtotal')
                            ->columnStart([
                                'default' => 1,
                                'lg' => 3
                            ])
                            ->extraAttributes([
                                'class' => '[&>div>div>div]:justify-end'
                            ])
                            ->inlineLabel(),
                        Infolists\Components\TextEntry::make('sisatagihan')
                            ->formatStateUsing(function ($state){
                                return 'Rp' . number_format($state, 0, ',', '.');
                            })
                            ->label('Sisa tagihan')
                            ->columnStart([
                                'default' => 1,
                                'lg' => 3
                            ])
                            ->extraAttributes([
                                'class' => '[&>div>div>div]:justify-end'
                            ])
                            ->inlineLabel()
                    ]),
            ])
        ]);
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\PointOfSale\Resources\SalesInvoiceResource\RelationManagers\PenjualanBayarRelationManager::class
        ];
    }
}
