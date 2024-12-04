<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\RelationManagers;

use App\Models\Penjualan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class PenjualanBayarRelationManager extends RelationManager
{
    protected static string $relationship = 'penjualanBayar';
    protected static ?string $title = 'Pembayaran';

    protected function storeSalesPayment($data, $model) {
        dump($model);
        return [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\ToggleButtons::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->extraAttributes([
                        'class' => ''
                    ])
                    ->default([
                        'tunai'
                    ])
                    ->required()
                    ->columnSpanFull()
                    ->gridDirection('row')
                    ->options(\App\Filament\PointOfSale\Enums\PaymentMethod::class),
                Forms\Components\TextInput::make('nominal')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->prefix('Rp'),
            ]);
    }

    public function table(Table $table): Table
    {
        $penjualan = DB::select('select toko_griyanaura.f_getsisatagihanpenjualan(?) as sisatagihan',[$this->getOwnerRecord()->transaksi_no])[0];
        return $table
            ->recordTitleAttribute('nominal')
            ->columns([
                Tables\Columns\TextColumn::make('nominal')
                    ->formatStateUsing(function ($record) {
                        return 'Rp' . \number_format($record->nominal + $record->kembalian, 0, ',', '.');
                    }),
                Tables\Columns\TextColumn::make('kembalian')
                    ->formatStateUsing(function ($state) {
                        return 'Rp' . \number_format($state, 0, ',', '.');
                    })
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->icon('heroicon-m-plus')
                    ->label('Tambah pembayaran')
                    ->disabled(!($penjualan->sisatagihan > 0))
                    ->modalWidth('md')
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->createAnother(false)
                    ->modalCancelActionLabel('Batal')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalHeading('Tambah Pembayaran')
                    ->using(function ($data, $model) {
                        $this->storeSalesPayment($data, $model);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Belum ada pembayaran')
            ->emptyStateDescription('Silahkan buat pembayaran terlebih dahulu')
            ->modelLabel('pembayaran');
    }
}
