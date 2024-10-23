<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PenyesuaianGudangDetail extends Model
{
    use  Compoships;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_penyesuaiangudangdetail';
    protected $table = 'toko_griyanaura.tr_penyesuaiangudangdetail';
    protected $guarded = [];

    public function produkPersediaan()
    {
        return $this->hasOne(ProdukPersediaan::class, ['kode_produkvarian', 'id_gudang'], ['kode_produkvarian', 'id_gudang'])->addSelect([
            'hargabeli_avg' => ProdukPersediaanDetail::select(DB::raw('(sum(hargabeli*coalesce(stok_in,0) - hargabeli*coalesce(stok_out,0))/nullif(sum(coalesce(stok_in,0))-sum(coalesce(stok_out,0)),0))::int'))
                ->whereColumn('toko_griyanaura.ms_produkpersediaandetail.id_persediaan', 'toko_griyanaura.ms_produkpersediaan.id_persediaan')
        ]);
    }
}
