<?php

namespace App\Filament\PointOfSale\Resources\SalesReturnResource\Pages;

use App\Filament\PointOfSale\Resources\SalesReturnResource;
use Filament\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components;
use Awcodes\TableRepeater;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditSalesReturn extends EditRecord
{
    protected static string $resource = SalesReturnResource::class;
    
    protected static ?string $title = 'Ubah Retur Penjualan';

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->load(['penjualanReturDetail', 'penjualan.penjualanDetail' => function ($query) {
            $query->with(['produkVarian']);
            $query->whereHas('produkVarian.produk', function ($q2) {
                $q2->where('in_stok', true);
            });
            $query->leftJoin(DB::raw("(select id_penjualandetail as id_penjualandetaildiretur, sum(qty) as jumlah_diretur from toko_griyanaura.tr_penjualanreturdetail group by id_penjualandetail) as x"), 'x.id_penjualandetaildiretur', 'toko_griyanaura.tr_penjualandetail.id_penjualandetail');
        }]);
        $this->fillForm();
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Batal');
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Simpan Perubahan');
    }

    public function getBreadcrumb(): string
    {
        return 'Ubah';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('Kembali')
                ->color('gray')
                ->icon('heroicon-s-arrow-left')
                ->url(url()->route('filament.pos.resources.sales-returns.index')),
            Actions\DeleteAction::make()
                ->label('Hapus Retur')
                ->icon('heroicon-s-trash'),
        ];
    }

    public function form(Form $form): Form
    {
        $returItems = $this->record->penjualanReturDetail->keyBy('id_penjualandetail');
        return parent::form($form)
            ->schema([
                Section::make()
                    ->schema([
                        Components\Grid::make([
                            'default' => 2,
                            'md' => 3
                        ])
                            ->schema([
                                Components\TextInput::make('transaksi_no')
                                    ->label('No. Transaksi')
                                    ->disabled()
                                    ->columnStart(1),
                                Components\TextInput::make('penjualan.transaksi_no')
                                    ->label('Transaksi Diretur')
                                    ->suffixAction(
                                        Components\Actions\Action::make('link')
                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                            ->iconButton()
                                            ->url(function ($record) {
                                                return url()->route('filament.pos.resources.sales-invoices.view', ['record' => $record->id_penjualan]);
                                            })
                                    )
                                    ->disabled(),
                                Components\Select::make('id_kontak')
                                    ->relationship('kontak', 'nama', function ($query, $state) {
                                        $query->where('id_kontak', $state)->limit(1);
                                    })
                                    ->label('Pelanggan')
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnStart(1),
                                Components\TextInput::make('penjualan.nama_customer')
                                    ->disabled()
                                    ->columnStart(2),
                                Components\DateTimePicker::make('tanggal')
                                    ->locale('id')
                                    ->native(false)
                                    ->displayFormat('d F Y H:i:s')
                                    ->columnStart([
                                        'default' => 2,
                                        'md' => 3
                                    ]),
                                TableRepeater\Components\TableRepeater::make('penjualan_detail')
                                    ->headers([
                                        TableRepeater\Header::make('Produk'),
                                        TableRepeater\Header::make('Jumlah Diretur/Qty'),
                                        TableRepeater\Header::make('Jumlah Retur'),
                                        TableRepeater\Header::make('Harga'),
                                        TableRepeater\Header::make('Diskon'),
                                        TableRepeater\Header::make('Total'),
                                    ])
                                    ->schema([
                                        Components\TextInput::make('produk_varian.varian')
                                            ->disabled()
                                            ->hiddenLabel(),
                                        Components\Placeholder::make('')
                                            ->content(function ($get) use ($returItems) {
                                                return ($get('jumlah_diretur') ? (number($get('jumlah_diretur')) - number($returItems[$get('id_penjualandetail')]?->qty ?? null)) : '0') . '/' . number($get('qty'));
                                            }),
                                        Components\TextInput::make('qty_diretur')
                                            ->live(debounce: 500)
                                            ->formatStateUsing(function ($get) use ($returItems) {
                                                return number($returItems[$get('id_penjualandetail')]?->qty ?? null);
                                            })
                                            ->afterStateUpdated(function ($get, $set) {
                                                $set('total', (int)((float)$get('qty_diretur') * (int)$get('harga') * (1-(float)$get('diskon')/100)));
                                            })
                                            ->maxValue(function ($get) use ($returItems) {
                                                return ($get('jumlah_diretur') - number($returItems[$get('id_penjualandetail')]?->qty ?? null)) + $get('qty');
                                            })
                                            ->minValue(0)
                                            ->numeric(),
                                        Components\TextInput::make('harga')
                                            ->prefix('Rp')
                                            ->disabled()
                                            ->extraInputAttributes([
                                                'class' => 'text-end'
                                            ]),
                                        Components\TextInput::make('diskon')
                                            ->suffix('%')
                                            ->disabled()
                                            ->extraInputAttributes([
                                                'class' => 'text-end'
                                            ]),
                                        Components\TextInput::make('total')
                                            ->prefix('Rp')
                                            ->disabled()
                                            ->formatStateUsing(function ($get, $set) {
                                                return (int)((float)$get('qty_diretur') * (int)$get('harga') * (1-(float)$get('diskon')/100));
                                            })
                                            ->extraInputAttributes([
                                                'class' => 'text-end'
                                            ]),
                                    ])
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->statePath('penjualan.penjualan_detail')
                                    ->columnSpanFull(),
                                Components\TextInput::make('totalraw')
                                    ->label('Subtotal')
                                    ->placeholder(function ($get, $set) {
                                        $set('totalraw', array_sum(array_map(function ($value) {return $value['total'];}, $get('penjualan.penjualan_detail'))));
                                    })
                                    ->prefix('Rp')
                                    ->columnStart([
                                        'default' => 1,
                                        'md' => 3
                                    ])
                                    ->columnSpan([
                                        'default' => 2,
                                        'md' => 1
                                    ])
                                    ->inlineLabel()
                                    ->extraInputAttributes([
                                        'class' => 'text-end'
                                    ])
                                    ->disabled(),
                                Components\TextInput::make('diskon')
                                    ->inlineLabel()
                                    ->suffix('%')
                                    ->disabled()
                                    ->columnStart([
                                        'default' => 1,
                                        'md' => 3
                                    ])
                                    ->columnSpan([
                                        'default' => 2,
                                        'md' => 1
                                    ])
                                    ->extraInputAttributes([
                                        'class' => 'text-end'
                                    ]),
                                Components\TextInput::make('grandtotal')
                                    ->placeholder(function ($get, $set) {
                                        $set('grandtotal', (int)($get('totalraw') * (1-(float)$get('diskon')/100)));
                                    })
                                    ->prefix('Rp')
                                    ->columnStart([
                                        'default' => 1,
                                        'md' => 3
                                    ])
                                    ->columnSpan([
                                        'default' => 2,
                                        'md' => 1
                                    ])
                                    ->inlineLabel()
                                    ->extraInputAttributes([
                                        'class' => 'text-end'
                                    ])
                                    ->disabled(),
                                Components\Textarea::make('catatan')
                                    ->rows(3)
                                    ->columnSpan([
                                        'default' => 2,
                                        'md' => 1
                                    ])
                            ])
                    ])
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record->toArray();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        dump($record, $data);
        return $record;
    }
}
