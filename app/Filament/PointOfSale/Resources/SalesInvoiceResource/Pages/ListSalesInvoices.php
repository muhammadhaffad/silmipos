<?php

namespace App\Filament\PointOfSale\Resources\SalesInvoiceResource\Pages;

use App\Filament\PointOfSale\Resources\SalesInvoiceResource;
use App\Models\Penjualan;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getBreadcrumb(): ?string
    {
        return 'Daftar';
    }

    public function getTabs(): array
    {
        /* $tabs = ['all' => Tab::make('All')->badge($this->getModel()::count())];
 
        $tiers = Tier::orderBy('order_column', 'asc')
            ->withCount('customers')
            ->get();
 
        foreach ($tiers as $tier) {
            $name = $tier->name;
            $slug = str($name)->slug()->toString();
 
            $tabs[$slug] = Tab::make($name)
                ->badge($tier->customers_count)
                ->modifyQueryUsing(function ($query) use ($tier) {
                    return $query->where('tier_id', $tier->id);
                });
        } */
        $countStatusPenjualan = Penjualan::addSelect([DB::raw('count(*) as all'), DB::raw("count(*) filter (where toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no) > 0) as belumlunas"), DB::raw("count(*) filter (where toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no) <= 0) as lunas")])->where('jenis', 'invoice')->first();
        $tabs = [
            'all' => Tab::make('Semua')
                ->badge($countStatusPenjualan->all),
            'lunas' => Tab::make('Lunas')->modifyQueryUsing(function ($query) {
                    $query->where(DB::raw('toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no)'), '<=', 0);
                })
                ->badge($countStatusPenjualan->lunas)
                ->badgeColor('success'),
            'belum_lunas' => Tab::make('Belum Lunas')->modifyQueryUsing(function ($query) {
                    $query->where(DB::raw('toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no)'), '>', 0);
                })
                ->badge($countStatusPenjualan->belumlunas)
                ->badgeColor('danger')
        ];
        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-m-plus')
                ->label('Penjualan'),
        ];
    }
}
