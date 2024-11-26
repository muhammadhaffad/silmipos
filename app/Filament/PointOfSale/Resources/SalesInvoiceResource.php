<?php

namespace App\Filament\PointOfSale\Resources;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;
use App\Filament\PointOfSale\Resources\SalesInvoiceResource\RelationManagers;
use App\Models\Penjualan;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $pluralModelLabel = 'Invoice Penjualan';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Penjualan';

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
                return $query->addSelect(['*',DB::raw('toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no) as sisatagihan')])->where('jenis', 'invoice')->with(['kontak', 'gudang', 'penjualanOrder']);
            })
            ->defaultSort('id_penjualan', 'desc')
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
                TextColumn::make('nama_customer')
                    ->label('Pelanggan')
                    ->state(function ($record) {
                        return $record->nama_customer ?: $record->kontak->nama;
                    })
                    ->searchable(query: function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->whereNull('nama_customer')->whereHas('kontak', function ($q) use ($search) {
                                $q->where('nama', 'ilike', "%{$search}%");
                            });
                        })
                        ->orWhere('nama_customer', 'ilike', "%{$search}%");
                    }),
                TextColumn::make('sisatagihan')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        if ($state > 0) {
                            return 'Belum Lunas';
                        } else {
                            return 'Lunas';
                        }
                    })
                    ->badge()
                    ->color(function ($state) {
                        if ($state > 0) {
                            return 'danger';
                        } else {
                            return 'success';
                        }
                    })
                    ->tooltip(function ($state) {
                        if ($state > 0) {
                            return 'Sisa tagihan: Rp' . number_format($state, 0, ',', '.');
                        }
                    }),
                TextColumn::make('catatan')
                    ->default('-'),
                TextColumn::make('grandtotal')
                    ->label('Grand total')
                    ->formatStateUsing(function ($state) {
                        return 'Rp' . number_format($state, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('print')
                    ->tooltip('Print receipt')
                    ->icon('heroicon-m-printer')
                    ->iconButton()
                    ->form([
                        Placeholder::make('')
                            ->content(function ($get) {
                                return new HtmlString(view('filament.pages.point-of-sale.sales-receipt', ['data' => $get('')])->render());
                            })
                            ->extraAttributes([
                                'class' => 'overflow-y-auto max-h-[calc(100vh-200px)]'
                            ]),
                        Actions::make([
                            Action::make('print')
                                ->extraAttributes([
                                    'class' => 'w-full'
                                ])
                                ->alpineClickHandler("printDiv('.print-only')")
                        ])
                    ])
                    ->mountUsing(function ($arguments, $form) {
                        $penjualan = \App\Models\Penjualan::with('penjualanDetail.produkVarian', 'kontak')->where('id_penjualan', $arguments['record']['id_penjualan'])->withSum('penjualanBayar as nominalbayar', 'nominal')->withSum('penjualanBayar as kembalian', 'kembalian')->first();
                        $form->fill($penjualan->toArray());
                    })
                    ->extraModalWindowAttributes([
                        'class' => '!w-[10cm]'
                    ])
                    ->label('Cetak Nota')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
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
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
