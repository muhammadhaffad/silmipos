<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use App\Models\Gudang;
use App\Models\ProdukVarian;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

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
                Section::make()
                    ->schema([
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                TextInput::make('transaksi_no')
                                    ->label('No. Transaksi')
                                    ->disabled()
                                    ->columnSpan(1)
                            ]),
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                Select::make('id_kontak')
                                    ->relationship('kontak', 'nama')
                                    ->label('Pelanggan')
                                    ->disabled()
                                    ->columnSpan(1),
                                TextInput::make('nama_customer')
                                    ->label('Nama pelanggan')
                                    ->columnSpan(1)
                            ]),
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                DatePicker::make('tanggal')
                                    ->label('Tanggal')
                                    ->disabled()
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->displayFormat('d M Y')
                                    ->locale('id')
                                    ->native(false),
                                DatePicker::make('tanggaltempo')
                                    ->label('Tanggal jatuh tempo')
                                    ->disabled()
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->displayFormat('d M Y')
                                    ->locale('id')
                                    ->native(false)
                            ]),
                        TableRepeater::make('detail_penjualan')
                            ->headers([
                                Header::make('Produk')->width('220px'),
                                Header::make('Gudang')->width('120px'),
                                Header::make('Qty')->width('120px'),
                                Header::make('Harga')->width('120px'),
                                Header::make('Diskon')->width('120px'),
                                Header::make('Total')->width('120px'),
                            ])
                            ->schema([
                                Select::make('kode_produkvarian')
                                    ->live(true)
                                    ->afterStateUpdated(function ($record, $get, $set) {
                                        if ($record == null) {
                                            $gudang = Gudang::find($get('id_gudang'));
                                            $produk = ProdukVarian::with(['produk', 'produkVarianHarga' => function ($q) use ($gudang) {
                                                $q->where('id_varianharga', $gudang?->default_varianharga ?: 1);
                                            }, 'produkPersediaan' => function ($q) use ($gudang) {
                                                $q->with('produkVarianHarga')->where('gdg.id_gudang', $gudang?->id_gudang)->first();
                                            }])->where('kode_produkvarian', $get('kode_produkvarian'))->first()?->toArray();
                                            if ($produk['produk_persediaan'] ?? false) {
                                                $set('harga', $produk['produk_persediaan'][0]['produk_varian_harga']['hargajual']);
                                            } else {
                                                $set('harga', $produk['produk_varian_harga']['hargajual'] ?? 0);
                                            }
                                        }
                                    })
                                    ->options(ProdukVarian::withLabelVarian()->get()->pluck('varian', 'kode_produkvarian'))
                                    ->getSearchResultsUsing(function ($search) {
                                        return ProdukVarian::withLabelVarian()->where('varian', 'ilike', "%{$search}%")->orWhere('kode_produkvarian', 'ilike', "%{$search}%")->limit(10)->get()->pluck('varian', 'kode_produkvarian');
                                    })
                                    ->optionsLimit(10)
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(function ($record) {
                                        return $record != null;
                                    }),
                                Select::make('id_gudang')
                                    ->label('Gudang')
                                    ->live(true)
                                    ->afterStateUpdated(function ($record, $get, $set) {
                                        if ($record == null) {
                                            $gudang = Gudang::find($get('id_gudang'));
                                            $produk = ProdukVarian::with(['produk', 'produkVarianHarga' => function ($q) use ($gudang) {
                                                $q->where('id_varianharga', $gudang?->default_varianharga ?: 1);
                                            }, 'produkPersediaan' => function ($q) use ($gudang) {
                                                $q->with('produkVarianHarga')->where('gdg.id_gudang', $gudang?->id_gudang)->first();
                                            }])->where('kode_produkvarian', $get('kode_produkvarian'))->first()?->toArray();
                                            if ($produk['produk_persediaan'] ?? false) {
                                                $set('harga', $produk['produk_persediaan'][0]['produk_varian_harga']['hargajual']);
                                            } else {
                                                $set('harga', $produk['produk_varian_harga']['hargajual'] ?? 0);
                                            }
                                        }
                                    })
                                    ->options(Gudang::all()->pluck('nama', 'id_gudang'))
                                    ->default(function () {
                                        return Gudang::first()->id_gudang;
                                    })
                                    ->native(false)
                                    ->selectablePlaceholder(false)
                                    ->disabled(function ($record) {
                                        return $record != null;
                                    }),

                                TextInput::make('qty')
                                    ->live(debounce: 500)
                                    ->formatStateUsing(function ($state) {
                                        return \number($state ?: 1);
                                    })
                                    ->minValue(0)
                                    ->maxValue(function ($record, $get) {
                                        if ($record?->produkPersediaan != null) {
                                            return \number($record?->produkPersediaan?->stok);
                                        } else {
                                            if ($get('kode_produkvarian') != null and $get('id_gudang') != null) {
                                                $persediaan = \App\Models\ProdukPersediaan::where('kode_produkvarian', $get('kode_produkvarian'))->where('id_gudang', $get('id_gudang'))->first();
                                                return \number($persediaan?->stok);
                                            } else {
                                                return 0;
                                            }
                                        }
                                    })
                                    ->helperText(function ($record, $get) {
                                        if ($record?->produkPersediaan != null) {
                                            return 'Sisa stok: ' . \number($record?->produkPersediaan?->stok);
                                        } else {
                                            if ($get('kode_produkvarian') != null and $get('id_gudang') != null) {
                                                $persediaan = \App\Models\ProdukPersediaan::where('kode_produkvarian', $get('kode_produkvarian'))->where('id_gudang', $get('id_gudang'))->first();
                                                return 'Sisa stok: ' . \number($persediaan?->stok);
                                            } else {
                                                return 'Sisa stok: -';
                                            }
                                        }
                                    })
                                    ->numeric(),
                                TextInput::make('harga')
                                    ->live(debounce: 500)
                                    ->numeric()
                                    ->formatStateUsing(function ($state) {
                                        return $state;
                                    }),
                                TextInput::make('diskon')
                                    ->live(debounce: 500)
                                    ->formatStateUsing(function ($state) {
                                        return \number($state);
                                    })
                                    ->default(0)
                                    ->numeric()
                                    ->maxValue(100)
                                    ->minValue(0),
                                TextInput::make('total')
                                    ->placeholder(function ($get, $set) {
                                        $set('total', (int)((int)($get('harga')) * (float)($get('qty')) * (1 - (float)($get('diskon')) / 100)));
                                    })
                                    ->disabled()

                            ])
                            ->relationship('penjualanDetail', function ($query) {
                                $query->orderBy('id_penjualandetail');
                                $query->with('produkVarian', 'gudang', 'produkPersediaan.produkVarianHarga');
                            })
                            ->dehydrated()
                            ->saveRelationshipsUsing(function ($state) {
                                return [];
                            })
                            ->columnSpanFull()
                    ])
                    ->columns(3)
                    ->extraAttributes([
                        'class' => ' [&_.table-repeater-container_tr_td]:!align-top [&_.table-repeater-container_td:nth-child(1)]:!sticky [&_.table-repeater-container_td:nth-child(1)]:dark:!bg-gray-900 [&_.table-repeater-container_td:nth-child(1)]:!z-50 [&_.table-repeater-container_td:nth-child(1)]:!left-0 [&_.table-repeater-container_th:nth-child(1)]:!sticky [&_.table-repeater-container_th:nth-child(1)]:!left-0 [&_.table-repeater-container_th:nth-child(1)]:dark:!bg-gray-900 [&_.table-repeater-container_th:nth-child(1)]:!z-50 [&_.table-repeater-container_td:last-child]:!sticky [&_.table-repeater-container_td:last-child]:dark:!bg-gray-900 [&_.table-repeater-container_td:last-child]:!z-50 [&_.table-repeater-container_td:last-child]:!right-0 [&_.table-repeater-container_th:last-child]:!sticky [&_.table-repeater-container_th:last-child]:!right-0 [&_.table-repeater-container_th:last-child]:dark:!bg-gray-900 [&_.table-repeater-container_th:last-child]:!z-50 [&_.table-repeater-container_td>*]:md:!w-[120px] [&_.table-repeater-container_td:nth-child(1)>*]:md:!w-[220px] [&_.table-repeater-container_td:last-child>*]:md:!w-[40px] '
                    ])
            ]);
    }

    // protected function mutateFormDataBeforeFill(array $data): array
    // {
    //     dd($data);
    // }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        dump($data);
        return $record;
    }
}
