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
            ->query(ProdukPersediaan::with('produkVarian.produk'))
            ->modelLabel('ProdukPersediaan')
            ->modifyQueryUsing($this->modifyQueryWithActiveTab(...))
            ->paginated()
            ->columns([
                Stack::make([
                    TextColumn::make('kode_produkvarian'),
                    TextColumn::make('produkVarian.produk.nama'),
                    TextColumn::make('produkVarian.varian'),
                    TextColumn::make('stok')
                ])
            ])
            ->contentGrid([
                'md' => 3,
                'xl' => 4
            ]);
    }

    public function mount() 
    {
        $this->loadDefaultActiveTab();
    }
}
