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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Resources\Concerns\HasTabs;
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
    protected $listeners = ['refreshTable' => '$refresh'];
    public $counter;
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
    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Pesanan Baru')
                ->schema([
                    Repeater::make('detail_pesanan')
                        ->schema([
                            Placeholder::make('produk_deskripsi')
                                ->content(function (Get $get/* $component */) {
                                    $title = $get('kode_produkvarian');
                                    $image = $get('image') ?: 'https://www.svgrepo.com/show/508699/landscape-placeholder.svg';
                                    $subTotal = number_format($get('hargajual') * $get('qty') * (1 - $get('diskon') / 100), 0, ',', '.');
                                    return new HtmlString(<<<HTML
                                        <div class="flex gap-2">
                                            <img src="{$image}" class="w-16 h-16 rounded" alt="" />
                                            <div>
                                                <span>{$title}</span>
                                                <span>Rp{$subTotal}</span>
                                            </div>
                                        </div> 
                                    HTML);
                                    // return $component->getContainer()->getRawState()['qty'];
                                })
                                ->hiddenLabel(),
                            TextInput::make('qty')
                                ->numeric()
                                ->hiddenLabel()
                                ->extraInputAttributes([
                                    'class' => 'text-center'
                                ])
                                ->prefix('Qty')
                                ->minValue(1)
                                ->maxValue(function ($component) {
                                    return $component->getContainer()->getRawState()['stok'];
                                })
                                ->live()
                                ->afterStateUpdated(function ($livewire, $state, $component) {
                                    $livewire->dispatch('refreshTable');
                                })
                        ])
                        ->columns(2)
                        ->addable(false)
                        ->reorderable(false)
                        ->hiddenLabel()
                        ->extraItemActions([
                            Action::make('abcd')
                                ->tooltip('Open product')
                                ->icon('heroicon-m-arrow-top-right-on-square')
                                ->action(function ($arguments) {
                                    dump($arguments);
                                })
                        ])
                        ->registerListeners([
                            'detail_pesanan::test' => [
                                function (Component $component, ?array $data): void {
                                    $statePath = $component->getStatePath();

                                    $sku = (string) $data['kode_produkvarian'];
                                    $livewire = $component->getLivewire();
                                    if (data_get($livewire, "{$statePath}.{$sku}")) {
                                        $currentItem = data_get($livewire, "{$statePath}.{$sku}");
                                        $currentItem['qty'] += $data['qty'];
                                        $data = $currentItem;
                                    } else {
                                        data_set($livewire, "{$statePath}.{$sku}", []);
                                    }
                                    $component->getChildComponentContainers()[$sku]->fill($data);
                                }
                            ]
                        ]),
                    Select::make('customer')
                        ->native(false),
                    TextInput::make('subtotal')
                        ->disabled(),
                    TextInput::make('diskon')
                        ->live(),
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
                                if (isset($this->data['detail_pesanan']) && isset($this->data['detail_pesanan'][$row['kode_produkvarian']])) {
                                    return $this->data['detail_pesanan'][$row['kode_produkvarian']]['qty'];
                                }
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
                'md' => 3,
                'xl' => 4
            ])
            ->recordAction('addToCart')
            ->deferLoading();
    }
    public function addToCart(ProdukPersediaan $record)
    {
        $record->load('produkVarianHarga');
        $data = [
            'kode_produkvarian' => $record->kode_produkvarian,
            'id_gudang' => $record->id_gudang,
            'qty' => 1,
            'hargajual' => $record->produkVarianHarga->hargajual,
            'diskon' => 0,
            'stok' => $record->stok
        ];
        return $this->dispatchFormEvent('detail_pesanan::test', $data);
    }
    public function mount()
    {
        $this->counter = 1;
        $this->loadDefaultActiveTab();
    }
}
