<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use App\Models\Gudang;
use App\Models\ProdukVarian;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;
    protected static ?string $title = 'Ubah Invoice Penjualan';
    public static string|Alignment $formActionsAlignment = Alignment::End;

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

    public function mount(int|string $record): void
    {
        parent::mount($record);
        FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, function () {
            return '<style>' . <<<'CSS'
                    .table-repeater-container {
                        overflow-x: scroll;
                    }
                    .table-repeater-container tr td {
                        vertical-align: top;
                    }
                    .table-repeater-container td:nth-child(1), .table-repeater-container th:nth-child(1) {
                        position: sticky;
                        z-index: 10;
                        left: 0px;
                    }
                    .table-repeater-container td:last-child, .table-repeater-container th:last-child {
                        position: sticky;
                        z-index: 10;
                        right: 0px;
                    }
            CSS . '</style>';
        });
        FilamentView::registerRenderHook(PanelsRenderHook::SCRIPTS_AFTER, function () {
            return '<script>' . <<<'JS'
                function waitForElm(selector, callback) {
                    const observer = new MutationObserver(function (mutations, mutationInstance) {
                        const elm = document.querySelector(selector);
                        if (elm) {
                            callback(elm);
                            mutationInstance.disconnect();
                        }
                    });
                    observer.observe(document, {
                        childList: true,
                        subtree:   true
                    });
                }

                waitForElm(`.table-repeater-container`, function (elm) {
                    console.info(elm);
                });
            JS . '</script>'; 
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Hapus Invoice')
                ->icon('heroicon-s-trash'),
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
                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('tambah_item')
                                ->label('Tambah baris')
                                ->icon('heroicon-m-plus')
                                ->color('gray')
                                ->action(function () {
                                    $this->dispatchFormEvent('penjualanDetail::add_item');
                                })
                        ]),
                        TableRepeater::make('detail_penjualan')
                            ->label('')
                            ->headers([
                                Header::make('Produk')->width('220px'),
                                Header::make('Gudang')->width('120px'),
                                Header::make('Qty')->width('100px'),
                                Header::make('Harga')->width('150px'),
                                Header::make('Diskon')->width('150px'),
                                Header::make('Total')->width('150px'),
                            ])
                            ->schema([
                                Select::make('kode_produkvarian')
                                    ->view('filament.pages.point-of-sale.components.select')
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
                                    ->view('filament.pages.point-of-sale.components.select')
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
                                    ->prefix('Rp')
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
                                    ->suffix('%')
                                    ->default(0)
                                    ->numeric()
                                    ->maxValue(100)
                                    ->minValue(0),
                                TextInput::make('total')
                                    ->prefix('Rp')
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
                            ->addable(false)
                            ->registerListeners([
                                'penjualanDetail::add_item' => [
                                    function (Repeater $component): void {
                                        $newUuid = $component->generateUuid();
                                        $items = array_merge(
                                          [$newUuid => []],
                                          $component->getState(),
                                        );
                                        $component->state($items);
                                        $component->getChildComponentContainer($newUuid)->fill();
                                        $component->collapsed(false, shouldMakeComponentCollapsible: false);
                                        $component->callAfterStateUpdated();
                                    }
                                ]
                            ])
                            ->columnSpanFull(),
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                Placeholder::make('subtotal')
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->content(function ($set, $get) {
                                        $map = Arr::map($get('detail_penjualan'), function ($item) {
                                            return $item['total'];
                                        });
                                        return 'Rp' . number_format(array_sum($map), 0, ',', '.');
                                    })
                                    ->extraAttributes([
                                        'class' => 'text-end'
                                    ])
                                    ->inlineLabel(),
                                TextInput::make('diskon')
                                    ->live(debounce: 500)
                                    ->formatStateUsing(function ($state) {
                                        return number($state);
                                    })
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->suffix('%')
                                    ->extraAttributes([
                                        'class' => 'max-w-[120px] ms-auto'
                                    ])
                                    ->inlineLabel(),
                                Placeholder::make('Total')
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->content(function ($set, $get) {
                                        $map = Arr::map($get('detail_penjualan'), function ($item) {
                                            return $item['total'];
                                        });
                                        return 'Rp' . number_format((int)(array_sum($map) * (1 - min((float)($get('diskon')), 100)/100)), 0, ',', '.');
                                    })
                                    ->extraAttributes([
                                        'class' => 'text-end'
                                    ])
                                    ->inlineLabel(),
                                Placeholder::make('Sisa tagihan')
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->content(function ($get) {
                                        return 'Rp' . number_format($get('sisatagihan'), 0, ',', '.');
                                    })
                                    ->extraAttributes([
                                        'class' => 'text-end'
                                    ])
                                    ->inlineLabel(),
                                Textarea::make('catatan')
                                    ->rows(4)
                            ])
                    ])
                    ->columns(3)
                    ->extraAttributes([
                        'class' => '
                        [&_.table-repeater-container_td:nth-child(1)]:dark:!bg-gray-900 
                        [&_.table-repeater-container_th:nth-child(1)]:dark:!bg-gray-900 
                        [&_.table-repeater-container_td:last-child]:dark:!bg-gray-900 
                        [&_.table-repeater-container_th:last-child]:dark:!bg-gray-900
                        [&_.table-repeater-container_td:nth-child(1)]:!bg-white 
                        [&_.table-repeater-container_th:nth-child(1)]:!bg-gray-100 
                        [&_.table-repeater-container_td:last-child]:!bg-white
                        [&_.table-repeater-container_th:last-child]:!bg-gray-100 
                        [&_.table-repeater-container_td:nth-child(1)>*]:md:!w-[220px] 
                        [&_.table-repeater-container_td:nth-child(2)>*]:md:!w-[100px] 
                        [&_.table-repeater-container_td:nth-child(3)>*]:md:!w-[100px] 
                        [&_.table-repeater-container_td:nth-child(4)>*]:md:!w-[150px] 
                        [&_.table-repeater-container_td:nth-child(5)>*]:md:!w-[100px] 
                        [&_.table-repeater-container_td:nth-child(6)>*]:md:!w-[150px] 
                        [&_.table-repeater-container_td:nth-child(7)>*]:md:!w-[40px]'
                    ])
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\PointOfSale\Resources\SalesInvoiceResource\RelationManagers\PenjualanBayarRelationManager::class
        ];
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
