<?php

namespace App\Filament\PointOfSale\Pages;

use App\Filament\PointOfSale\Traits\InteractWithTabsTrait;
use App\Models\Gudang;
use App\Models\Kontak;
use App\Models\ProdukPersediaan;
use App\Services\Core\Sales\SalesInvoiceService;
use App\Services\Core\Sales\SalesPaymentService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Support\Facades\FilamentView;
use Filament\Support\RawJs;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Masterminds\HTML5;

use function Filament\Support\format_number;

class Cashier extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractWithTabsTrait;
    use HasTabs;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $title = null;
    protected static string $view = 'filament.pages.point-of-sale.cashier';
    public ?array $data = [];
    protected $listeners = ['refresh-table' => '$refresh', 'calc-total-qty' => 'calcTotalQty'];
    protected $salesInvoiceService;
    protected $salesPaymentService;
    public $counter;
    public $totalQty;

    public function __construct()
    {
        $this->salesInvoiceService = new SalesInvoiceService();
        $this->salesPaymentService = new SalesPaymentService();
    }

    public function mount()
    {
        $this->totalQty = [];
        $this->counter = 1;
        $this->loadDefaultActiveTab();
        $this->form->fill();
        // FilamentView::registerRenderHook(PanelsRenderHook::STYLES_AFTER, function () {
        //     return '<style>' . <<<'CSS'
        //         @media print {
        //             body > * {
        //                 display: none !important;
        //             }
        //             .print-only > * {
        //                 display: block !important;
        //             }
        //         }
        //     CSS . '</style>';
        // });
        FilamentView::registerRenderHook(PanelsRenderHook::BODY_END, function () {
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
                    window.addEventListener('searchItems', (e) => {
                        const items = e.detail[0].items;
                        const search = document.getElementById('data.search').value;
                        console.info(search);
                        if (items) {
                            Object.entries(items).forEach(entry => {
                                const [key, value] = entry;
                                // console.log(key, value);
                                waitForElm(`[x-sortable-item="${key}"]`, function (elm) {
                                    if (! `${value.nama_produk} ${value.nama_varian}`.toLowerCase().includes(search.toLowerCase())) {
                                        elm.classList.add('hidden');
                                    } else {
                                        elm.classList.remove('hidden');
                                    }
                                });
                            });
                        }
                    });
                    function printDiv(selector) {
                        const printContent = document.querySelector(selector).innerHTML;
                        // Buat window baru
                        const printWindow = window.open("", "_blank", "width=800,height=800");
                        printWindow.document.open();
                        printWindow.document.write(`${printContent}`);
                        printWindow.window.print();
                        setTimeout(() => {
                            printWindow.document.close();
                        }, 10);
                    }
            JS . '</script>';
        });
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function create($saveAndPay = true): void
    {
        $this->form->validate();
        $record = $this->form->getRawState();
        $record['id_gudang'] = end($record['penjualanDetail'])['id_gudang'];
        $record['tanggal'] = \date('Y-m-d H:i:s');
        $record['catatan'] = null; //sementara null
        $record['tanggaltempo'] = \date('Y-m-d H:i:s', \strtotime('+ 1 day'));
        if (!isset($record['diskon'])) {
            $record['diskon'] = 0;
        }
        try {
            // $invoice = $this->salesInvoiceService->storeSalesInvoice($record);
            $this->data['transaksi_no'] = '123'; //$invoice['transaksi_no'];
            $this->data['kontak_nama'] = Kontak::find($record['id_kontak'], 'nama')->nama;
            $this->data['tanggal'] = date('Y-m-d H:i:s');//$invoice['tanggal'];
            $this->data['id_penjualan'] = 23;//$invoice['id_penjualan'];
            if ($saveAndPay) {
                $this->mountAction('payment');
            } else {
                Notification::make()
                    ->title('Sukses menyimpan pesanan')
                    ->body('Pesanan Anda dapat dilihat di menu Penjualan')
                    ->success()
                    ->send();
            }
            $this->data = [];
            $this->totalQty = [];
        } catch (ValidationException $e) {
            Notification::make()
                ->title('422 Unprocessable Entity, please contact developer.')
                ->danger()
                ->send();
        }
    }

    public function createPayment($record): void 
    {
        $data = [
            'id_kontak' => $record['id_kontak'],
            'tanggal' => \date('Y-m-d H:i:s'),
            'catatan' => null,
            'penjualanAlokasiPembayaran' => [
                [
                    'id_penjualan' => (string)$record['id_penjualan'],
                    'nominalbayar' => $this->cleanFormatNumber($record['bayar'])
                ],
            ],
            'total' => $this->cleanFormatNumber($record['bayar'])
        ];
        try {
            // $this->salesPaymentService->storePayment($data);
            $this->replaceMountedAction('printPayment', ['record' => $record]);
            Notification::make()
                ->title('Sukses melakukan pembayaran')
                ->success()
                ->send();
        } catch (\Exception $th) {
            Notification::make()
                ->title('Internal Server Error')
                ->body($th->getMessage())
                ->danger()
                ->send();
        }
    }

    public function printPaymentAction()
    {
        return \Filament\Actions\Action::make('printPayment')
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
                'class' => 'w-[10cm]'
            ])
            ->label('Cetak Nota')
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }
    
    public function cleanFormatNumber($number) {
        return \str_replace(['.',','],['','.'],$number);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->schema([
                    TextInput::make('search')
                        ->hiddenLabel()
                        ->prefixIcon('heroicon-m-magnifying-glass')
                        ->live(debounce: '500ms')
                        ->placeholder(function (Get $get, $component) {
                            $items = $get('penjualanDetail');
                            $this->dispatch('searchItems', ['items' => $items]);
                            return 'Cari item keranjang ...';
                        }),
                    Repeater::make('penjualanDetail')
                        ->schema([
                            Placeholder::make('')
                                ->live()
                                ->content(function (Get $get, Set $set) {
                                    $productName = $get('nama_produk');
                                    $variantName = $get('nama_varian');
                                    $image = $get('image') ?: 'https://www.svgrepo.com/show/508699/landscape-placeholder.svg';
                                    $subTotal = \number_format((int)$get('harga') * (float)$get('qty') * (1 - $get('diskon') / 100), 0, ',', '.');
                                    return new HtmlString(<<<HTML
                                        <div class="flex gap-3">
                                            <img src="{$image}" class="w-16 h-16 rounded" alt="" />
                                            <div class="space-y-1 w-full">
                                                <div class="w-full flex gap-2 justify-between items-center">
                                                    <span class="block text-sm truncate">{$productName}</span>
                                                    <span class="block whitespace-nowrap text-xs text-gray-500">Gudang {$get('nama_gudang')}</span>
                                                </div>
                                                <span class="block text-xs truncate">{$variantName}</span>
                                                <span class="block text-xs">Rp{$subTotal}</span>
                                            </div>
                                        </div> 
                                    HTML);
                                    // return $component->getContainer()->getRawState()['qty'];
                                })
                                ->hiddenLabel(),
                            \Filament\Forms\Components\Split::make([
                                Placeholder::make('harga')
                                    ->content(function (Get $get) {
                                        if (!$get('diskon') or $get('diskon') == 0) {
                                            $price = 'Rp' . number_format($get('harga'), 0, ',', '.');
                                        } else {
                                            $oldPrice = 'Rp' . number_format($get('harga'), 0, ',', '.');
                                            $newPrice = 'Rp' . number_format($get('harga') * (1 - $get('diskon') / 100), 0, ',', '.');
                                            $discount = $get('diskon');
                                            $price = <<<HTML
                                                <span>
                                                    <s>{$oldPrice}</s>&nbsp<span class="text-red-600">-$discount%</span>
                                                </span>
                                                <span class="font-bold">{$newPrice}<span>
                                            HTML;
                                        }
                                        return new HtmlString(<<<HTML
                                            <div class="h-[32px] flex flex-col grow justify-center -space-y-1 text-xs">{$price}</div>
                                        HTML);
                                    })
                                    ->extraAttributes([
                                        'class' => 'grow'
                                    ])
                                    ->grow(false)
                                    ->hiddenLabel(),
                                \Filament\Forms\Components\Split::make([
                                    Actions::make([
                                        Action::make('delete')
                                            ->iconButton()
                                            ->icon('heroicon-m-trash')
                                            ->color('danger')
                                            ->action(function (Get $get, Set $set, $component, $action) {
                                                $livewire = $component->getLivewire();
                                                data_forget($livewire, $component->getStatePath());
                                            })
                                            ->after(function (Get $get, Set $set) {
                                                $this->calcTotalQty();
                                            })
                                    ])->grow(false)->extraAttributes([
                                        'class' => 'justify-center !h-[32px]'
                                    ]),
                                    TextInput::make('qty')
                                        ->numeric()
                                        ->hiddenLabel()
                                        ->extraInputAttributes([
                                            'class' => 'text-center text-sm !p-[4px]'
                                        ])
                                        ->maxWidth('10')
                                        ->suffixAction(
                                            Action::make('detail_item')
                                                ->modalWidth('md')
                                                ->label(function (Get $get) {
                                                    $productName = $get("nama_produk", true);
                                                    $variantName = $get("nama_varian", true);
                                                    return "{$productName} {$variantName}";
                                                })
                                                ->color('gray')
                                                ->fillForm(function (Get $get) {
                                                    return [
                                                        'diskon' => $get('diskon'),
                                                        'harga' => $get('harga')
                                                    ];
                                                })
                                                ->form([
                                                    \Filament\Forms\Components\Split::make([
                                                        TextInput::make('harga')
                                                            ->label('Harga')
                                                            ->numeric()
                                                            ->mask(RawJs::make(<<<'JS'
                                                                $money($input, ',', '.', 2)
                                                            JS))
                                                            ->stripCharacters('.')
                                                            ->extraAlpineAttributes([
                                                                'x-ref' => 'input',
                                                                'x-on:keyup' => '$refs.input.blur(); $refs.input.focus()'
                                                            ])
                                                            ->prefix('Rp'),
                                                        TextInput::make('diskon')
                                                            ->label('Diskon (%)')
                                                            ->numeric()
                                                            ->maxValue(100)
                                                            ->suffix('%')
                                                    ])
                                                ])
                                                ->tooltip('Atur harga dan diskon')
                                                ->icon('heroicon-m-ellipsis-vertical')
                                                ->action(function ($data, Set $set) {
                                                    $set('harga', $data['harga']);
                                                    $set("diskon", $data['diskon']);
                                                })
                                        )
                                        ->live(debounce: 200)
                                        ->minValue(1)
                                        ->maxValue(function (Get $get) {
                                            return $get('produk_distok') ? $get('stok') : 1;
                                        })
                                        ->afterStateUpdated(function ($livewire, $state, $component) {
                                            $livewire->validateOnly($component->getStatePath());
                                            $livewire->dispatch('refresh-table');
                                            $this->calcTotalQty();
                                        })
                                ])
                                    ->grow(false)
                                    ->extraAttributes([
                                        'class' => '!gap-3'
                                    ])
                            ])
                                ->extraAttributes([
                                    'class' => '[&>div:nth-child(1)]:md:flex-1 [&>div:nth-child(1)]:md:w-full [&>div:nth-child(1)]:lg:flex-none [&>div:nth-child(1)]:lg:w-auto [&>div:nth-child(1)]:xl:flex-1 [&>div:nth-child(1)]:xl:w-full [&>div:nth-child(2)]:flex-1 [&>div:nth-child(2)]:w-full'
                                ])
                        ])
                        ->view('filament.pages.point-of-sale.components.repeater.index')
                        ->default([])
                        ->required()
                        ->addable(false)
                        ->reorderable(false)
                        ->hiddenLabel()
                        ->deletable(false)
                        ->live()
                        ->extraAttributes([
                            'class' => '[&>ul>div>li>div>div]:!gap-3 scrollbar overflow-y-auto p-[1px] max-h-[calc(100vh-416px)]',
                            'searchable' => true
                        ])
                        ->registerListeners([
                            'penjualanDetail::add_to_cart' => [
                                function (Component $component, ?array $data): void {
                                    $statePath = $component->getStatePath();
                                    $unique = (string) Str::uuid();
                                    $livewire = $component->getLivewire();
                                    data_set($livewire, "{$statePath}.{$unique}", []);
                                    $component->getChildComponentContainers()[$unique]->fill($data);
                                    $this->calcTotalQty();
                                }
                            ]
                        ]),
                    Select::make('id_kontak')
                        ->label('Pelanggan')
                        ->rules('required|numeric')
                        ->placeholder('Pilih pelanggan')
                        ->options(
                            Kontak::where('jenis_kontak', 'customer')
                                ->get()
                                ->pluck('nama', 'id_kontak')
                                ->unique()
                        )
                        ->native(false),
                    \Filament\Forms\Components\Split::make([
                        TextInput::make('total')
                            ->numeric()
                            ->placeholder(function (Set $set, Get $get) {
                                $total = 0;
                                foreach ($get('penjualanDetail') ?: [] as $key => $item) {
                                    $total += (int)((int)$item['harga'] * (float)$item['qty'] * (1 - (float)$item['diskon'] / 100));
                                }
                                $set('total', \number_format($total, 0, ',', '.'));
                            })
                            ->mask(RawJs::make(<<<'JS'
                                $money($input, ',', '.', 2)
                            JS))
                            ->stripCharacters('.')
                            ->readOnly()
                            ->live()
                            ->prefix('Rp')
                            ->grow(false),
                        TextInput::make('diskon')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->live(debounce: '500ms')
                            ->placeholder(0)
                            ->grow(false),
                    ])->extraAttributes([
                        'class' => '[&>div:nth-child(1)]:w-2/3 [&>div:nth-child(2)]:w-1/3'
                    ]),
                    Actions::make([
                        Action::make('pay')
                            ->label(function (Get $get, Set $set) {
                                $grandTotal = (int)(str_replace(['.', ','], ['', '.'], $get('total')) * (1 - (float)$get('diskon') / 100));
                                $set('grandtotal', $grandTotal);
                                if ($grandTotal) {
                                    return 'BAYAR - Rp' . number_format($get('grandtotal'), 0, ',', '.');
                                } else {
                                    return 'BAYAR';
                                }
                            })
                            ->extraAttributes([
                                'class' => 'w-full',
                                'type' => 'submit'
                            ]),
                        Action::make('save')
                            ->label('SIMPAN')
                            ->extraAttributes([
                                'class' => 'w-full',
                            ])
                            ->action(function () {
                                $this->create(false);
                            })
                            ->color('gray')
                    ])
                        ->extraAttributes([
                            'class' => 'mt-4'
                        ])
                ])
                ->extraAttributes([
                    'class' => 'md:max-h-[calc(100vh)] [&>div>div>div]:!gap-3'
                ])
        ])->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProdukPersediaan::with('produkVarian.produk', 'gudang', 'produkVarianHarga'))
            ->modelLabel('ProdukPersediaan')
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->paginated()
            ->paginationPageOptions([8, 16, 32, 64, 'all'])
            ->filters([
                SelectFilter::make('id_gudang')
                    ->label('Gudang')
                    ->multiple()
                    ->options(Gudang::all()
                        ->pluck('nama', 'id_gudang')
                        ->unique())
                    ->native(false)
            ], layout: FiltersLayout::Dropdown)
            ->columns([
                Stack::make([
                    ImageColumn::make('gambar')
                        ->defaultImageUrl('https://www.svgrepo.com/show/508699/landscape-placeholder.svg')
                        ->extraImgAttributes([
                            'class' => 'rounded'
                        ])
                        ->height('100%')
                        ->width('100%'),
                    \Filament\Tables\Columns\Layout\Split::make([
                        TextColumn::make('')
                            ->default(function (ProdukPersediaan $row) {
                                if (isset($this->totalQty["{$row['kode_produkvarian']}_{$row['id_gudang']}"])) {
                                    return $this->totalQty["{$row['kode_produkvarian']}_{$row['id_gudang']}"];
                                }
                                return null;
                            })
                            ->extraAttributes([
                                'class' => 'absolute top-0 start-0 end-0 justify-end -translate-y-2 translate-x-2'
                            ])

                            ->badge()
                            ->color(' !bg-blue-600 !text-white')
                    ]),
                    TextColumn::make('kode_produkvarian')
                        ->size('xs')
                        ->hidden()
                        ->searchable(),
                    TextColumn::make('produkVarian.produk.nama')
                        ->searchable(query: function (Builder $query) {
                            $query->whereHas('produkVarian', function ($q) {
                                $q->select(['toko_griyanaura.ms_produkvarian.*', DB::raw("string_agg(coalesce(av.nama,''), '/' order by pav.id_produkattributvalue) as varian")])
                                    ->join('toko_griyanaura.ms_produk as prd', 'toko_griyanaura.ms_produkvarian.id_produk', 'prd.id_produk')
                                    ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
                                    ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
                                    ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
                                    ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
                                    ->having(DB::raw("prd.nama || ' '|| string_agg(coalesce(av.nama,''), ' ' order by pav.id_produkattributvalue)"), 'ilike', '%' . $this->tableSearch . '%')
                                    ->groupBy('prd.id_produk', 'toko_griyanaura.ms_produkvarian.kode_produkvarian');
                            });
                        }),
                    TextColumn::make('produkVarian.varian')
                        ->size('xs')
                        ->default(function (ProdukPersediaan $row) {
                            return $row->produkVarian->produk->nama;
                        })
                        ->extraAttributes([
                            'class' => 'overflow-hidden'
                        ]),
                    \Filament\Tables\Columns\Layout\Split::make([
                        TextColumn::make('stok')->formatStateUsing(function ($state) {
                            return 'Stok: ' . number($state);
                        })
                            ->color(function ($state, ProdukPersediaan $row) {
                                if (number($state) <= $row->produkVarian?->produk?->minstok) {
                                    return 'danger';
                                }
                                return 'info';
                            }),
                        TextColumn::make('produkVarianHarga.hargajual')
                            ->formatStateUsing(function ($state) {
                                return 'Rp' . \number_format($state, 0, ',', '.');
                            })
                            ->alignEnd(),
                    ])
                        ->extraAttributes([
                            'class' => '!flex-wrap !gap-0 !space-y-0'
                        ]),
                    TextColumn::make('gudang.nama')
                        ->formatStateUsing(function (ProdukPersediaan $row) {
                            return $row->gudang?->nama ? ("Gudang {$row->gudang?->nama}") : 'Tidak distok';
                        })
                        ->default('Tidak distok')
                        ->color('success')
                        ->extraAttributes([
                            'class' => 'whitespace-nowrap'
                        ])
                        ->size('xs'),
                ])->space(2)
            ])
            ->contentGrid([
                'default' => 2,
                'lg' => 3,
                'xl' => 4
            ])
            ->recordAction('addToCart')
            ->deferLoading();
    }

    public function paymentAction()
    {
        $pecahanActions = [];
        $pecahans = [1000, 2000, 5000, 10000, 20000, 50000, 100000];
        $pecahanActions[] = Action::make('uang_pas')
            ->label('UANG PAS')
            ->extraAttributes([
                'class' => 'text-nowrap !font-normal'
            ])
            ->color('success')
            ->action(function ($set, Get $get) {
                $set('bayar', number_format($get('grandtotal'),0,',','.'));
            });
        foreach ($pecahans as $pecahan) {
            $pecahanActions[] = Action::make($pecahan)
                ->action(function ($set, $get) use ($pecahan) {
                    $set('bayar', number_format((int)$this->cleanFormatNumber($get('bayar')) + $pecahan, 0, ',', '.'));
                })
                ->extraAttributes([
                    'class' => 'text-nowrap !font-normal'
                ])
                ->color('gray')
                ->label('Rp'.number_format($pecahan, 0, ','. '.'));
        }
        $pecahanActions[] = Action::make('clear')
            ->label('CLEAR')
            ->extraAttributes([
                'class' => 'text-nowrap !font-normal'
            ])
            ->color('danger')
            ->action(function ($set, Get $get) {
                $set('bayar', 0);
            });
        
        return \Filament\Actions\Action::make('payment')
            ->label('')
            ->form([
                \Filament\Forms\Components\Split::make([
                    Section::make()
                        ->schema([
                            Placeholder::make('')
                                ->content(function ($get) {
                                    $tanggal = date('d/M/Y H:i', \strtotime($get('tanggal')));
                                    return new HtmlString(<<<HTML
                                        <div class="flex justify-between items-start">
                                            <div class="flex flex-col gap-1">
                                                <span class="text-sm">#{$get('transaksi_no')}</span>
                                                <div class="flex gap-1 items-center text-sm">
                                                    <span>
                                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-5.5-2.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM10 12a5.99 5.99 0 0 0-4.793 2.39A6.483 6.483 0 0 0 10 16.5a6.483 6.483 0 0 0 4.793-2.11A5.99 5.99 0 0 0 10 12Z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                    <span class="font-normal">{$get('kontak_nama')}</span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col">
                                                <div class="flex gap-1 items-center text-sm">
                                                    <span>
                                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-5">
                                                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                                                        </svg>
                                                    </span>
                                                    <span class="text-sm font-normal">{$tanggal}</span>
                                                </div>
                                            </div>
                                        </div>
                                    HTML);
                                }),
                            Repeater::make('penjualanDetail')
                                ->schema([
                                    Placeholder::make('')
                                        ->content(function (Get $get) {
                                            $subTotal = number_format((int)((int)$get('harga')*(float)$get('qty')), 0, ',', '.');
                                            $subTotalAfterDiscount = number_format((int)($this->cleanFormatNumber($subTotal)*(1-(float)$get('diskon')/100)), 0, ',', '.');
                                            if ($get('diskon')) {
                                                $subTotal = <<<HTML
                                                    <span class="block"><s>Rp{$subTotal}</s> <span class="text-red-600">-{$get('diskon')}%</span></span>
                                                    <span class="block font-bold">Rp{$subTotalAfterDiscount}</span>
                                                HTML;
                                            } else {
                                                $subTotal = <<<HTML
                                                    <span class="block font-bold">Rp{$subTotalAfterDiscount}</span>
                                                HTML;
                                            }
                                            return new HtmlString(<<<HTML
                                                <div class="flex justify-between items-center">
                                                    <div class="flex items-center gap-2">
                                                        <div>
                                                            <span class="bg-[rgb(var(--primary-500))] rounded-full px-2 py-1 text-white">
                                                                {$get('qty')}×
                                                            </span>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="block">{$get('nama_produk')}</span>
                                                            <span class="block text-xs">{$get('nama_varian')}</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col -space-y-2">
                                                        {$subTotal}
                                                    </div>
                                                </div>
                                            HTML);
                                        })
                                ])
                                ->extraAttributes([
                                    'class' => '[&>ul>div]:!gap-2 min-h-[362px] h-[calc(100vh-320px)] overflow-y-auto p-[1px]'
                                ])
                                ->hiddenLabel()
                                ->deletable(false)
                                ->reorderable(false)
                                ->addable(false),
                            Placeholder::make('')
                                ->content(function ($get) {
                                    $grandTotal = number_format($get('grandtotal'), 0, ',', '.');
                                    $diskon = $get('diskon') ?: 0;
                                    $totalItems = array_sum(array_column($get('penjualanDetail'), 'qty'));
                                    return new HtmlString(<<<HTML
                                        <div>
                                            <div class="flex justify-between">
                                                <span>Total</span>
                                                <span>Rp{$get('total')}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Diskon</span>
                                                <span>{$diskon}%</span>
                                            </div>
                                            <div class="flex justify-between font-bold">
                                                <span>Grand Total ({$totalItems} items)</span>
                                                <span>Rp{$grandTotal}</span>
                                            </div>
                                        </div>
                                    HTML);
                                    return $get('total');
                                })
                        ]),
                    Section::make()
                        ->schema([
                            Hidden::make('grandtotal'),
                            // Hidden::make('bayar'),
                            Placeholder::make('')
                                ->content(function ($get) {
                                    $grandTotal = \number_format($get('grandtotal'), 0, ',', '.');
                                    $kembalian = \number_format(max((int)str_replace(['.',','],['', '.'],$get('bayar'))-(int)$get('grandtotal'),0),0,',','.');
                                    return new HtmlString(<<<HTML
                                        <div class="w-full grid grid-cols-2 gap-3">
                                            <div class="">
                                                <span>Tagihan</span>
                                                <h3 class="font-bold text-xl">Rp{$grandTotal}</h3>
                                            </div>
                                            <div class="">
                                                <span>Kembalian</span>
                                                <h3 class="font-bold text-xl">Rp{$kembalian}</h3>
                                            </div>
                                        </div>
                                    HTML);
                                }),
                            ToggleButtons::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->extraAttributes([
                                    'class' => '[&>div>label]:p-4 [&>div>label]:w-full'
                                ])
                                ->required()
                                ->columns(2)
                                ->gridDirection('row')
                                ->options(\App\Filament\PointOfSale\Enums\PaymentMethod::class),
                            TextInput::make('bayar')
                                ->label('Bayar')
                                ->required()
                                ->live(true)
                                ->mask(RawJs::make(<<<'JS'
                                    $money($input, ',', '.', 2)
                                JS))
                                ->stripCharacters('.')
                                ->extraAlpineAttributes([
                                    ':value' => 'inputBayar',
                                    'x-ref' => 'input',
                                    'x-on:keyup' => '$refs.input.blur(); $refs.input.focus()',
                                ])
                                ->prefix('Rp'),
                            Actions::make($pecahanActions)
                                ->extraAttributes([
                                    'class' => '[&>div]:!grid [&>div]:!gap-3 [&>div]:!grid-cols-3'
                                ]),
                            Actions::make([
                                Action::make('pay-now')
                                    ->label('BAYAR')
                                    ->extraAttributes([
                                        'class' => 'grow',
                                        'type' => 'submit'
                                    ]),
                                Action::make('pay-later')
                                    ->label('BAYAR NANTI')
                                    ->extraAttributes([
                                        'class' => 'w-full'
                                    ])
                                    ->color('gray')
                                    ->dispatch('close-modal', ['id' => "{$this->getId()}-action"])
                            ])->extraAttributes([
                                'class' => 'flex'
                            ])
                        ])
                ])
                    ->from('md')
                    ->columns(2)
            ])
            ->action(function () {
                $record = $this->getMountedActionForm($this->getMountedAction())->getRawState();
                $this->createPayment($record);
            })
            ->fillForm($this->data)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->closeModalByEscaping(false)
            ->closeModalByClickingAway(false)
            ->modalCloseButton(false);
    }

    public function calcTotalQty()
    {
        if (isset($this->data['penjualanDetail'])) {
            $this->totalQty = collect($this->data['penjualanDetail'])
                ->groupBy(function ($val) {
                    return "{$val['kode_produkvarian']}_{$val['id_gudang']}";
                }) // Mengelompokkan berdasarkan 'kode'
                ->map(function ($items) {
                    return $items->reduce(function ($carry, $item) {
                        return (float)$carry + (float)$item['qty']; // Menambahkan qty secara manual
                    }, 0); // Menjumlahkan qty dalam setiap grup
                })
                ->toArray();
        } else {
            $this->totalQty = [];
        }
    }
    public function addToCart(ProdukPersediaan $record)
    {
        $record->load('produkVarianHarga', 'gudang');
        if ($record->stok > 0 or !$record->produkVarian->produk->in_stok) {
            if (($this->totalQty["{$record->kode_produkvarian}_{$record->id_gudang}"] ?? 0) >= $record->stok and $record->produkVarian->produk->in_stok) {
                Notification::make()
                    ->title('Stok tidak mencukupi')
                    ->danger()
                    ->send();
            } else {
                $data = [
                    'nama_produk' => $record->produkVarian->produk->nama,
                    'nama_varian' => $record->produkVarian->varian,
                    'produk_distok' => (bool)$record->produkVarian->produk->in_stok,
                    'kode_produkvarian' => $record->kode_produkvarian,
                    'id_gudang' => $record->id_gudang,
                    'nama_gudang' => $record?->gudang?->nama,
                    'qty' => 1,
                    'harga' => $record->produkVarianHarga->hargajual,
                    'diskon' => 0,
                    'stok' => $record->stok,
                ];
                $this->dispatchFormEvent('penjualanDetail::add_to_cart', $data);
            }
        } else {
            Notification::make()
                ->title('Stok kosong')
                ->danger()
                ->send();
        }
    }

    
    /* public function getTabs(): array
    {
        $tabs = [
            'All' => Tab::make('All')
        ];
        $gudang = Gudang::all()
            ->pluck('nama', 'id_gudang')
            ->unique();
        foreach ($gudang as $idGudang => $namaGudang) {
            $tabs[$namaGudang] = Tab::make($namaGudang)->modifyQueryUsing(function (Builder $query) use ($idGudang) {
                return $query->where('id_gudang', $idGudang);
            });
        }
        return $tabs;
    } */

    /* public function getTableRecordKey(Model $record): string
    {
        return $record->id_persediaan . '-' . $record->produkVarian->produk->id_produk;
    } */
}
