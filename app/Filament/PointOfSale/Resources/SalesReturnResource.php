<?php

namespace App\Filament\PointOfSale\Resources;

use App\Filament\PointOfSale\Resources\SalesReturnResource\Pages;
use App\Filament\PointOfSale\Resources\SalesReturnResource\RelationManagers;
use App\Models\PenjualanRetur;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesReturnResource extends Resource
{
    protected static ?string $model = PenjualanRetur::class;

    protected static ?string $navigationIcon = 'fas-rotate-left';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $pluralModelLabel = 'Retur Penjualan';

    protected static ?string $navigationLabel = 'Retur Penjualan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                return $query->select()->where('jenis', 'invoice')->with(['kontak', 'penjualan']);
            })
            ->defaultSort('id_penjualanretur', 'desc')
            ->columns([
                TextColumn::make('transaksi_no')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->formatStateUsing(function ($state) {
                        return date('d/M/Y H:i:s', strtotime($state));
                    })
                    ->sortable(),
                TextColumn::make('penjualan.transaksi_no')
                    ->label('Transaksi Diretur')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('penjualan.nama_customer')
                    ->label('Pelanggan')
                    ->state(function ($record) {  
                        return $record->penjualan->nama_customer ?: $record->kontak->nama;
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->whereHas('penjualan', function ($q) {
                                $q->whereNull('nama_customer');
                            })->whereHas('kontak', function ($q) use ($search) {
                                $q->where('nama', 'ilike', "%{$search}%");
                            });
                        })
                        ->orWhereHas('penjualan', function ($q) use ($search) {
                            $q->where('nama_customer', 'ilike', "%{$search}%");
                        });
                    }),
                TextColumn::make('catatan')
                    ->formatStateUsing(function ($state) {
                        return $state ?: '-';
                    })
                    ->default('-'),
                TextColumn::make('grandtotal')
                    ->label('Total Retur')
                    ->formatStateUsing(function ($state) {
                        return 'Rp' . number_format($state, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('penjualan.grandtotal')
                    ->label('Total Invoice')
                    ->formatStateUsing(function ($state) {
                        return 'Rp' . number_format($state, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->sortable()
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesReturn::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
        ];
    }
}
