<?php

namespace App\Filament\PointOfSale\Resources;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;
use App\Filament\PointOfSale\Resources\SalesInvoiceResource\RelationManagers;
use App\Models\Penjualan;
use App\Services\Core\Sales\SalesInvoiceService;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $pluralModelLabel = 'Invoice Penjualan';

    protected static ?string $navigationIcon = 'fas-bag-shopping';

    protected static ?string $navigationLabel = 'Penjualan';

    protected $salesInvoiceService;

    public function __construct()
    {
        $this->salesInvoiceService = new SalesInvoiceService;
    }

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
                TextColumn::make('status')
                    ->label('Status')
                    ->default(function ($record) {
                        if ($record->sisatagihan > 0) {
                            return 'BELUM LUNAS';
                        } else {
                            return 'LUNAS';
                        }
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->sisatagihan > 0) {
                            return 'danger';
                        } else {
                            return 'success';
                        }
                    }),
                TextColumn::make('catatan')
                    ->default('-'),
                TextColumn::make('sisatagihan')
                    ->label('Sisa tagihan')
                    ->formatStateUsing(function ($state) {
                        return 'Rp' . number_format($state, 0, ',', '.');
                    })
                    ->sortable(),
                TextColumn::make('grandtotal')
                    ->label('Grand total')
                    ->formatStateUsing(function ($state) {
                        return 'Rp' . number_format($state, 0, ',', '.');
                    })
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('sales_at')
                    ->form([
                        DatePicker::make('sales_from')
                            ->label('Dari tanggal')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->locale('id')
                            ->placeholder('Pilih tanggal'),
                        DatePicker::make('sales_until')
                            ->label('Sampai tanggal')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->locale('id')
                            ->placeholder('Pilih tanggal')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['sales_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['sales_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                 
                        if ($data['sales_from'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Penjualan dari ' . \Carbon\Carbon::parse($data['sales_from'])->toFormattedDateString())
                                ->removeField('sales_from');
                        }
                 
                        if ($data['sales_until'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Penjualan sampai ' . \Carbon\Carbon::parse($data['sales_until'])->toFormattedDateString())
                                ->removeField('sales_until');
                        }
                 
                        return $indicators;
                    })
            ])
            ->filtersTriggerAction(function (\Filament\Tables\Actions\Action $action) {
                $action->icon('heroicon-m-adjustments-horizontal');
            })
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('Lihat'),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Ubah'),
                Tables\Actions\DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Hapus')
                    ->action(function ($record, $livewire) {
                        app($livewire->getResource())->deleteSalesInvoice($record);
                    }),
                Tables\Actions\Action::make('print')
                    ->color('warning')
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
                    ->mountUsing(function ($arguments, $form, $record) {
                        $penjualan = \App\Models\Penjualan::with('penjualanDetail.produkVarian', 'kontak')->where('id_penjualan', $record->id_penjualan)->withSum('penjualanBayar as nominalbayar', 'nominal')->withSum('penjualanBayar as kembalian', 'kembalian')->first();
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
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->label('Hapus Penjualan')
                        ->action(function ($records, $livewire) {
                            app($livewire->getResource())->deleteBulkSalesInvoice($records);
                        }),
                ]),
            ])
            ->selectCurrentPageOnly();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->select()->addSelect(DB::raw('toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no) as sisatagihan'));
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
            'view' => Pages\ViewSalesInvoice::route('/{record}')
        ];
    }

    protected function deleteSalesInvoice($record) {
        try {
            $this->salesInvoiceService->deleteSalesInvoice($record->id_penjualan);
            Notification::make()
                ->title('Berhasil menghapus invoice penjualan')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal menghapus invoice penjualan')
                ->body($e->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function deleteBulkSalesInvoice($records) {
        $fails = 0;
        $success = 0;
        foreach ($records as $record) {
            try {
                $this->salesInvoiceService->deleteSalesInvoice($record->id_penjualan);
                $success++;
            } catch (\Exception $e) {
                $fails++;
            }
        }
        if ($fails > 0) {
            Notification::make()
                ->title("Terdapat $success item berhasil dihapus.")
                ->body("$fails item gagal dihapus.")
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Semua item berhasil dihapus.')
                ->success()
                ->send();
        }
    }
}
