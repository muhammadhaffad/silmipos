<?php

namespace App\Filament\PointOfSale\Pages;

use App\Filament\PointOfSale\Traits\InteractWithTabsTrait;
use App\Models\Gudang;
use App\Models\ProdukPersediaan;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Resources\Concerns\HasTabs;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class Cashier extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractWithTabsTrait;
    use HasTabs;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $title = 'Point Of Sale';
    protected static string $view = 'filament.pages.point-of-sale.cashier';

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

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('test')
        ]);
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
                    Split::make([
                        TextColumn::make('gudang.nama')
                            ->formatStateUsing(function (ProdukPersediaan $row) {
                                return $row->gudang?->nama ? ("Gudang {$row->gudang?->nama}") : 'Tidak distok';
                            })
                            ->default('Tidak distok')
                            ->color('success')
                            ->size('xs')
                            ->badge(),
                        TextColumn::make('')
                            ->default('')
                            ->icon('heroicon-o-shopping-bag')
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
                        ->placeholder(function (ProdukPersediaan $row) {
                            return $row->produkVarian->produk->nama;
                        })
                        ->extraAttributes([
                            'class' => 'overflow-hidden'
                        ]),
                    Split::make([

                        TextColumn::make('stok')->formatStateUsing(function ($state) {
                            return 'Stok: ' . number($state);
                        })
                            ->badge()
                            ->color(function ($state, ProdukPersediaan $row) {
                                if (number($state) <= $row->produkVarian?->produk?->minstok) {
                                    return 'danger';
                                } 
                                return 'info';
                            })
                            ->hidden(function (ProdukPersediaan $row) {
                                return !$row->produkVarian?->produk?->in_stok;
                            }),
                        TextColumn::make('produkVarianHarga.hargajual')
                            ->formatStateUsing(function ($state) {
                                return 'Rp' . \number_format($state, 0, ',', '.');
                            })
                            ->alignEnd(),
                    ])
                ])->space(1)
            ])
            ->contentGrid([
                'md' => 3,
                'xl' => 4
            ])
            ->recordAction('testClick')
            ->deferLoading();
    }
    public function testClick($x, ProdukPersediaan $record)
    {
        $this->counter++;
    }
    public function mount()
    {
        $this->counter = 1;
        $this->loadDefaultActiveTab();
    }
}
