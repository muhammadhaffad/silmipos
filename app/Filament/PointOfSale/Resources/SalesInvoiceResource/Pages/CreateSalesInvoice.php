<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use App\Services\Core\Sales\SalesInvoiceService;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;
    protected static ?string $title = 'Buat Invoice Penjualan';
    public static string|Alignment $formActionsAlignment = Alignment::End;
    protected $salesInvoiceService;

    public function __construct()
    {
        $this->salesInvoiceService = new SalesInvoiceService;
    }

    public function getBreadcrumb(): string
    {
        return 'Baru';
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Batal');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Buat Penjualan');   
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Buat & Buat Penjualan Lagi');   
    }

    public function mount(): void
    {
        parent::mount();
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
                                    ->placeholder('[AUTO]')
                            ]),
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                Select::make('id_kontak')
                                    ->relationship('kontak', 'nama')
                                    ->searchable()
                                    ->label('Pelanggan')
                                    ->default(\App\Models\Kontak::where('jenis_kontak', 'customer')->orderBy('id_kontak')->first()->id_kontak)
                                    ->optionsLimit(10)
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->required(),
                                TextInput::make('nama_customer')
                                    ->label('Nama pelanggan')
                                    ->columnSpan(1)
                                    ->required()
                                    ->autofocus()
                            ]),
                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                DateTimePicker::make('tanggal')
                                    ->label('Tanggal')
                                    ->default(now())
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->displayFormat('d M Y H:i:s')
                                    ->locale('id')
                                    ->required()
                                    ->native(false),
                                DateTimePicker::make('tanggaltempo')
                                    ->label('Tanggal jatuh tempo')
                                    ->default(now()->addDay())
                                    ->columnStart([
                                        'default' => 1,
                                        'lg' => 3
                                    ])
                                    ->displayFormat('d M Y H:i:s')
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
                        TableRepeater::make('penjualanDetail')
                            ->required()
                            ->label('')
                            ->headers([
                                Header::make('Produk')->width('220px;font-size: .875rem;'),
                                Header::make('Gudang')->width('120px;font-size: .875rem;'),
                                Header::make('Qty')->width('100px;font-size: .875rem;'),
                                Header::make('Harga')->width('150px;font-size: .875rem;'),
                                Header::make('Diskon')->width('150px;font-size: .875rem;'),
                                Header::make('Total')->width('150px;font-size: .875rem;'),
                            ])
                            ->schema([
                                Select::make('kode_produkvarian')
                                    ->required()
                                    ->view('filament.pages.point-of-sale.components.select')
                                    ->live(true)
                                    ->afterStateUpdated(function ($record, $get, $set) {
                                        if ($record == null) {
                                            $gudang = \App\Models\Gudang::find($get('id_gudang'));
                                            $produk = \App\Models\ProdukVarian::with(['produk', 'produkVarianHarga' => function ($q) use ($gudang) {
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
                                    ->options(\App\Models\ProdukVarian::withLabelVarian()->get()->pluck('varian', 'kode_produkvarian'))
                                    ->getSearchResultsUsing(function ($search) {
                                        return \App\Models\ProdukVarian::withLabelVarian()->where('varian', 'ilike', "%{$search}%")->orWhere('kode_produkvarian', 'ilike', "%{$search}%")->limit(10)->get()->pluck('varian', 'kode_produkvarian');
                                    })
                                    ->optionsLimit(10)
                                    ->searchable()
                                    ->native(false)
                                    ->disabled(function ($record) {
                                        return $record != null;
                                    }),
                                Select::make('id_gudang')
                                    ->required()
                                    ->view('filament.pages.point-of-sale.components.select')
                                    ->label('Gudang')
                                    ->live(true)
                                    ->afterStateUpdated(function ($record, $get, $set) {
                                        if ($record == null) {
                                            $gudang = \App\Models\Gudang::find($get('id_gudang'));
                                            $produk = \App\Models\ProdukVarian::with(['produk', 'produkVarianHarga' => function ($q) use ($gudang) {
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
                                    ->options(\App\Models\Gudang::all()->pluck('nama', 'id_gudang'))
                                    ->default(function () {
                                        return \App\Models\Gudang::first()->id_gudang;
                                    })
                                    ->native(false)
                                    ->selectablePlaceholder(false)
                                    ->disabled(function ($record) {
                                        return $record != null;
                                    }),

                                TextInput::make('qty')
                                    ->required()
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
                                                if (!$persediaan) {
                                                    $persediaan = \App\Models\ProdukPersediaan::where('kode_produkvarian', $get('kode_produkvarian'))->first();
                                                }
                                                if (!(bool)$persediaan->produkVarian()->first()->produk()->first()->in_stok) return 1;
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
                                                if (!$persediaan) {
                                                    $persediaan = \App\Models\ProdukPersediaan::where('kode_produkvarian', $get('kode_produkvarian'))->first();
                                                }
                                                if (!(bool)$persediaan->produkVarian()->first()->produk()->first()->in_stok) return 'Sisa stok: 1';
                                                return 'Sisa stok: ' . \number($persediaan?->stok);
                                            } else {
                                                return 'Sisa stok: -';
                                            }
                                        }
                                    })
                                    ->numeric(),
                                TextInput::make('harga')
                                    ->required()
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
                                        $map = \Illuminate\Support\Arr::map($get('penjualanDetail'), function ($item) {
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
                                        $map = \Illuminate\Support\Arr::map($get('penjualanDetail'), function ($item) {
                                            return $item['total'];
                                        });
                                        return 'Rp' . number_format((int)(array_sum($map) * (1 - min((float)($get('diskon')), 100)/100)), 0, ',', '.');
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

    protected function handleRecordCreation(array $data): Model
    {
        $data['id_gudang'] = end($data['penjualanDetail'])['id_gudang'];
        if (!isset($record['diskon'])) {
            $record['diskon'] = 0;
        }
        try {
            $invoice = $this->salesInvoiceService->storeSalesInvoice($data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('422 Unprocessable Entity, please contact developer.')
                ->danger()
                ->send();
            $this->halt();
        } catch (\Exception $e) {
            Notification::make()
                ->title('500 Internal Server Error, please contact developer.')
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->halt();
        }
        return $invoice;   
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Berhasil membuat invoice penjualan.';
    } 
}
