<?php

namespace App\Filament\PointOfSale\Pages;

use App\Filament\PointOfSale\Traits\InteractWithTabsTrait;
use App\Models\Gudang;
use App\Models\ProdukPersediaan;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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

    public function getTableRecordKey(Model $record): string
    {
        return $record->id_persediaan || '-' || $record->produk;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProdukPersediaan::with('produkVarian.produk', 'gudang', 'produkVarianHarga'))
            ->modelLabel('ProdukPersediaan')
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->paginated()
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
                    TextColumn::make('')
                        ->default(1)
                        ->extraAttributes([
                            'class' => 'mb-2'
                        ])
                        ->alignEnd()
                        ->badge(),
                    TextColumn::make('kode_produkvarian')
                        ->size('xs')
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
                        })
                        ->formatStateUsing(function (ProdukPersediaan $row) {
                            return $row->produkVarian->produk->nama . ' ' . $row->produkVarian->varian;
                        }),
                    TextColumn::make('produkVarianHarga.hargajual')
                        ->money('idr'),
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
                        ->extraAttributes([
                            'class' => 'mt-2'
                        ])
                        ->hidden(function (ProdukPersediaan $row) {
                            return !$row->produkVarian?->produk?->in_stok;
                        })
                ]),
                Split::make([
                    TextColumn::make('produkVarian.produk.in_stok')->formatStateUsing(function (ProdukPersediaan $row) {
                        if ($row->produkVarian->produk->in_stok) 
                            return 'Di stok';
                        else 
                            return 'Tidak di stok';
                    })->color(function ($state) {
                        if ($state) 
                            return 'success';
                        else
                            return 'warning';
                    })->badge(),
                    TextColumn::make('gudang.nama')->formatStateUsing(function (ProdukPersediaan $row) {
                        return 'Gudang ' . $row->gudang->nama;
                    })->color('success')->badge()
                ])->extraAttributes([
                    'class' => 'mt-2',
                    'style' => 'width: fit-content; gap: .5rem'
                ])
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
