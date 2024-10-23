<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PenjualanDetail extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualandetail';
    protected $primaryKey = 'id_penjualandetail';
    protected $guarded = [];

    use Compoships;

    public function produkVarian() {
        return $this->hasOne(ProdukVarian::class, 'kode_produkvarian', 'kode_produkvarian')
            ->select(['toko_griyanaura.ms_produkvarian.*', DB::raw("prd.nama_produk || ' ' || string_agg(coalesce(av.nama, ''), ' ' order by pav.id_produkattributvalue) as varian"), DB::raw("json_agg(json_build_object(coalesce(pav.id_produkattribut,0),coalesce(pav.id_attributvalue,0)) order by pav.id_produkattributvalue) as varian_id")])
            ->leftJoin(DB::raw("(select id_produk, nama as nama_produk from toko_griyanaura.ms_produk) as prd"), 'prd.id_produk', 'toko_griyanaura.ms_produkvarian.id_produk')
            ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
            ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
            ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
            ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
            ->groupBy('toko_griyanaura.ms_produkvarian.kode_produkvarian', 'prd.nama_produk');
    }
    public function produkPersediaan() {
        return $this->hasOne(ProdukPersediaan::class, ['kode_produkvarian', 'id_gudang'], ['kode_produkvarian', 'id_gudang'])->addSelect(['hargabeli_avg' => ProdukPersediaanDetail::select(DB::raw('(sum(hargabeli*coalesce(stok_in,0) - hargabeli*coalesce(stok_out,0))/nullif(sum(coalesce(stok_in,0))-sum(coalesce(stok_out,0)),0))::int'))
            ->whereColumn('toko_griyanaura.ms_produkpersediaandetail.id_persediaan', 'toko_griyanaura.ms_produkpersediaan.id_persediaan')
        ]);
    }
}
