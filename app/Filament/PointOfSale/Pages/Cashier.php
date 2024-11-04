<?php

namespace App\Filament\PointOfSale\Pages;

use App\Filament\PointOfSale\Traits\InteractWithTabsTrait;
use App\Models\Gudang;
use App\Models\ProdukPersediaan;
use App\Models\ProdukVarian;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Pages\Page;
use Filament\Resources\Components\Tab;
use Filament\Resources\Concerns\HasTabs;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class Cashier extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractWithTabsTrait;
    use HasTabs;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $title = 'Point Of Sale';
    protected static string $view = 'filament.pages.point-of-sale.cashier';

    public function getTabs(): array
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
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProdukPersediaan::with('produkVarian.produk', 'gudang'))
            ->modelLabel('ProdukPersediaan')
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->paginated()
            ->columns([
                Stack::make([
                    TextColumn::make('kode_produkvarian')->searchable(),
                    TextColumn::make('produkVarian.produk.nama')->searchable(),
                    TextColumn::make('produkVarian.varian')->searchable(query: function (Builder $query) {
                        $query->whereHas('produkVarian', function ($q) {
                            $q->select(['toko_griyanaura.ms_produkvarian.*', DB::raw("string_agg(coalesce(av.nama,''), '/' order by pav.id_produkattributvalue) as varian"), DB::raw("json_agg(json_build_object(coalesce(pav.id_produkattribut,0),coalesce(pav.id_attributvalue,0)) order by pav.id_produkattributvalue) as varian_id")])
                            ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
                            ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
                            ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
                            ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
                            ->having(DB::raw("string_agg(coalesce(av.nama,''), '/' order by pav.id_produkattributvalue)"), 'ilike', $this->tableSearch)
                            ->groupBy('toko_griyanaura.ms_produkvarian.kode_produkvarian');
                        });
                    }),
                    TextColumn::make('stok'),
                    TextColumn::make('gudang.nama')->badge()
                ])
            ])
            ->contentGrid([
                'md' => 3,
                'xl' => 4
            ])
            ->deferLoading();
    }

    public function mount() 
    {
        $this->loadDefaultActiveTab();
    }
}
