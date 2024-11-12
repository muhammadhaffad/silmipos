<?php

namespace App\Filament\PointOfSale\Pages;

use App\Filament\PointOfSale\Traits\InteractWithTabsTrait;
use App\Models\Gudang;
use App\Models\ProdukPersediaan;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Split as FormSplit;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Resources\Concerns\HasTabs;
use Filament\Support\RawJs;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

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
    public $counter;
    public $totalQty;

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

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function create(): void {
        $this->form->getState();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make()
                ->schema([
                    Repeater::make('detail_pesanan')
                        ->schema([
                            Placeholder::make('produk_deskripsi')
                                ->content(function (Get $get, Set $set) {
                                    $productName = $get('nama_produk');
                                    $variantName = $get('nama_varian');
                                    $image = $get('image') ?: 'https://www.svgrepo.com/show/508699/landscape-placeholder.svg';
                                    $subTotal = (int)$get('hargajual') * (float)$get('qty') * (1 - $get('diskon') / 100);
                                    $set('subtotal', $subTotal);
                                    $total = Arr::map($get('../'), function ($item) {
                                        return $item['subtotal'];
                                    });
                                    $set('../../subtotal', array_sum($total));
                                    $subTotal = number_format($subTotal, 0, ',', '.');
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
                            FormSplit::make([
                                Placeholder::make('hargajual')
                                    ->content(function (Get $get) {
                                        if (!$get('diskon') or $get('diskon') == 0) {
                                            $price = 'Rp' . number_format($get('hargajual'), 0, ',', '.');
                                        } else {
                                            $oldPrice = 'Rp' . number_format($get('hargajual'), 0, ',', '.');
                                            $newPrice = 'Rp' . number_format($get('hargajual') * (1 - $get('diskon') / 100), 0, ',', '.');
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
                                FormSplit::make([
                                    Actions::make([
                                        Action::make('delete')
                                            ->iconButton()
                                            ->icon('heroicon-m-trash')
                                            ->color('danger')
                                            ->action(function (Get $get, Set $set, $component, $action) {
                                                $livewire = $component->getLivewire();
                                                data_forget($livewire, $component->getStatePath());
                                            })
                                            ->after(function () {
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
                                                        'hargajual' => $get('hargajual')
                                                    ];
                                                })
                                                ->form([
                                                    FormSplit::make([
                                                        TextInput::make('hargajual')
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
                                                    $set("hargajual", $data['hargajual']);
                                                    $set("diskon", $data['diskon']);
                                                })
                                        )
                                        ->live()
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
                        ->addable(false)
                        ->reorderable(false)
                        ->hiddenLabel()
                        ->deletable(false)
                        ->extraAttributes([
                            'class' => '[&>ul>div>li>div>div]:!gap-3 scrollbar overflow-y-auto p-[1px] max-h-[calc(100vh-200px)]'
                        ])
                        ->registerListeners([
                            'detail_pesanan::add_to_cart' => [
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
                    Select::make('customer')
                        ->native(false),
                    FormSplit::make([
                        TextInput::make('subtotal')
                            ->mask(RawJs::make(<<<'JS'
                                $money($input, ',', '.', 2)
                            JS))
                            ->stripCharacters('.')
                            ->extraAlpineAttributes([
                                'x-ref' => 'input',
                                'x-on:keyup' => '$refs.input.blur(); $refs.input.focus()'
                            ])
                            ->numeric()
                            ->live()
                            ->placeholder(function (Get $get) {
                                $map = Arr::map($get('detail_pesanan'), function ($item) {
                                    return $item['subtotal'];
                                });
                                return array_sum($map);
                            })                
                            ->prefix('Rp')
                            ->readOnly()
                            ->grow(false),
                        TextInput::make('diskon')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('%')
                            ->maxValue(100)
                            ->live()
                            ->grow(false),
                    ])->extraAttributes([
                        'class' => '[&>div:nth-child(1)]:w-2/3 [&>div:nth-child(2)]:w-1/3'
                    ]),
                    TextInput::make('total')
                        ->disabled(),
                    Actions::make([
                        Action::make('test')
                            ->label('Pembayaran')
                            ->button()
                            ->extraAttributes([
                                'class' => 'w-full'
                            ])
                    ])
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
            ], layout: FiltersLayout::AboveContent)
            ->columns([
                Stack::make([
                    ImageColumn::make('gambar')
                        ->defaultImageUrl('https://www.svgrepo.com/show/508699/landscape-placeholder.svg')
                        ->extraImgAttributes([
                            'class' => 'rounded'
                        ])
                        ->height('100%')
                        ->width('100%'),
                    Split::make([
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
                    Split::make([
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

    public function calcTotalQty()
    {
        if (isset($this->data['detail_pesanan'])) {
            $this->totalQty = collect($this->data['detail_pesanan'])
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
                    'hargajual' => $record->produkVarianHarga->hargajual,
                    'diskon' => 0,
                    'stok' => $record->stok,
                ];
                $data['subtotal'] = (int) $data['hargajual'] * (float) $data['qty'] * (1 - (float) $data['diskon']/ 100);
                $this->dispatchFormEvent('detail_pesanan::add_to_cart', $data);
            }
        } else {
            Notification::make()
                ->title('Stok kosong')
                ->danger()
                ->send();
        }
    }
    public function mount()
    {
        $this->totalQty = [];
        $this->counter = 1;
        $this->loadDefaultActiveTab();
        $this->form->fill();
    }
}
