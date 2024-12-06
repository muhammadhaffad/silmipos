<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\RelationManagers;

use App\Models\Penjualan;
use App\Services\Core\Sales\SalesPaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
    protected $salesPaymentService;

    public function __construct()
    {
        $this->salesPaymentService = new SalesPaymentService;
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
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal Pembayaran')
                    ->formatStateUsing(function ($state) {
                        \Carbon\Carbon::setLocale('id');
                        return \Carbon\Carbon::parse($state)->translatedFormat('D, d F Y H:i:s');
                    }),
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
                    ->modalSubmitActionLabel('Bayar')
                    ->modalHeading('Tambah Pembayaran')
                    ->action(function ($data, $model) {
                        $this->storeSalesInvoicePayment($data, $model);
                    })
                    ->after(function ($livewire) { 
                        $livewire->dispatch('refreshFormPenjualan');
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalWidth('md')
                    ->modalHeading('Ubah nominal bayar')
                    ->modalCancelActionLabel('Batal')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->form([
                        Forms\Components\TextInput::make('nominal')
                            ->formatStateUsing(function ($get) {
                                return $get('nominal') + $get('kembalian');
                            })
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->prefix('Rp')
                    ])
                    ->action(function ($data, $model, $record) {
                        $this->updateSalesInvoicePayment($data, $model, $record);
                    })
                    ->after(function ($livewire) { 
                        $livewire->dispatch('refreshFormPenjualan');
                    }),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Delete Payment')
                    ->action(function ($record) {
                        $this->deleteSalesInvoicePayment($record);
                    })
                    ->after(function ($livewire) { 
                        $livewire->dispatch('refreshFormPenjualan');
                    }),
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

    protected function storeSalesInvoicePayment($data, $model) {
        $data = [
            'id_kontak' => $this->getOwnerRecord()->id_kontak,
            'tanggal' => \date('Y-m-d H:i:s'),
            'catatan' => null,
            'penjualanAlokasiPembayaran' => [
                [
                    'id_penjualan' => (string)$this->getOwnerRecord()->id_penjualan,
                    'nominalbayar' => (int)$data['nominal']
                ],
            ],
            'total' => (int)$data['nominal']
        ];
        try {
            $payment = $this->salesPaymentService->storePayment($data);
            Notification::make()
                ->title('Sukses melakukan pembayaran')
                ->success()
                ->send();
            return $payment->penjualanAlokasiPembayaran->first();
        } catch (\Exception $th) {
            Notification::make()
                ->title('Internal Server Error')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }
    protected function updateSalesInvoicePayment($data, $model, $record) {
        $record->load('penjualanPembayaran');
        $payment = $record->penjualanPembayaran;
        $data = [
            'tanggal' => $payment->tanggal,
            'catatan' => null,
            'penjualanAlokasiPembayaran' => [
                [
                    'id_penjualanalokasipembayaran' => $record->id_penjualanalokasipembayaran,
                    'id_penjualan' => (string)$this->getOwnerRecord()->id_penjualan,
                    'nominalbayar' => (int)$data['nominal'],
                    '_remove_' => 0
                ],
            ],
            'total' => (int)$data['nominal']
        ];
        try {
            $payment = $this->salesPaymentService->updatePayment($payment->id_penjualanpembayaran, $data);
            Notification::make()
                ->title('Sukses mengubah nominal pembayaran')
                ->success()
                ->send();
            return $payment->penjualanAlokasiPembayaran->first();
        } catch (\Exception $th) {
            Notification::make()
                ->title('Internal Server Error')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }
    protected function deleteSalesInvoicePayment($record) {
        $record->load('penjualanPembayaran');
        $payment = $record->penjualanPembayaran;
        try {
            $payment = $this->salesPaymentService->deletePayment($payment->id_penjualanpembayaran);
            Notification::make()
                ->title('Sukses menghapus nominal pembayaran')
                ->success()
                ->send();
            return $payment->penjualanAlokasiPembayaran->first();
        } catch (\Exception $th) {
            Notification::make()
                ->title('Internal Server Error')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }
}
