<?php

namespace App\Filament\PointOfSale\Resources\SalesReturnResource\Pages;

use App\Filament\PointOfSale\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components;
use Awcodes\TableRepeater;
use Illuminate\Support\Facades\DB;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Form $form): Form
    {
        return parent::form($form)
            ->schema([
                Section::make([
                    Components\Grid::make([
                        'default' => 2,
                        'md' => 3
                    ])
                        ->schema([
                            Components\TextInput::make('transaksi_no')
                                ->label('No. Transaksi')
                                ->disabled()
                                ->columnSpan([
                                    'default' => 2,
                                    'md' => 1
                                ])
                                ->columnStart(1),
                            Components\Group::make()
                                ->relationship('penjualan')
                                ->schema([
                                    Components\TextInput::make('transaksi_no')
                                        ->label('Transaksi Diretur')
                                        ->suffixAction(
                                            Components\Actions\Action::make('link')
                                                ->icon('heroicon-m-arrow-top-right-on-square')
                                                ->iconButton()
                                                ->url(function ($record) {
                                                    return url()->route('filament.pos.resources.sales-invoices.view', ['record' => $record->id_penjualan]);
                                                })
                                        )
                                        ->disabled()
                                ])
                                ->columnStart(2),
                            Components\Select::make('id_kontak')
                                ->relationship('kontak', 'nama')
                                ->label('Pelanggan')
                                ->disabled()
                                ->dehydrated()
                                ->columnStart(1),
                            Components\Group::make()
                                ->relationship('penjualan')
                                ->schema([
                                    Components\TextInput::make('nama_customer')
                                        ->disabled()
                                ])
                                ->columnStart(2),
                            Components\Group::make()
                                ->relationship('penjualan')
                                ->schema([
                                    TableRepeater\Components\TableRepeater::make('penjualanDetail')
                                        ->headers([
                                            TableRepeater\Header::make('Produk'),
                                            TableRepeater\Header::make('Jumlah Diretur/Qty')
                                        ])
                                        ->schema([
                                            Components\Group::make([
                                                Components\TextInput::make('varian')
                                                    ->disabled()
                                                    ->hiddenLabel(),
                                            ])->relationship('produkVarian'),
                                            Components\TextInput::make('qty_diretur')
                                                ->prefix(function ($record) {
                                                    return ($record->jumlah_diretur ? number($record->jumlah_diretur) : 0) . '/' . ($record->qty ? number($record->qty) : 0);
                                                })
                                                // ->content(function ($record) {
                                                //     return ($record->jumlah_diretur ? number($record->jumlah_diretur) : 0) . "/" . ($record->qty ? number($record->qty) : 0);
                                                // })
                                                // ->formatStateUsing(function ($state) {
                                                //     return $state ? number($state) : 0;
                                                // })
                                        ])
                                        ->addable(false)
                                        ->deletable(false)
                                        ->relationship('penjualanDetail', function ($query) {
                                            $query->with(['produkVarian']);
                                            $query->whereHas('produkVarian.produk', function ($q2) {
                                                $q2->where('in_stok', true);
                                            });
                                            $query->leftJoin(DB::raw("(select id_penjualandetail as id_penjualandetaildiretur, sum(qty) as jumlah_diretur from toko_griyanaura.tr_penjualanreturdetail group by id_penjualandetail) as x"), 'x.id_penjualandetaildiretur', 'toko_griyanaura.tr_penjualandetail.id_penjualandetail');
                                        })
                                ])
                                ->columnSpanFull()
                        ])
                ])
            ]);
    }
}
