<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProdukVarian extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'kode_produkvarian';
    protected $keyType = 'string';
    protected $table = 'toko_griyanaura.ms_produkvarian';
    protected $guarded = [];
    protected $hidden = ['inserted_at', 'updated_at', 'inserted_by', 'updated_by'];
    public $incrementing = false;

    public function scopeWithLabelVarian(Builder $query) {
        $subQuery = $this->select(['toko_griyanaura.ms_produkvarian.*', DB::raw("toko_griyanaura.ms_produkvarian.kode_produkvarian || ' - ' || p.nama || ' ' || string_agg(coalesce(av.nama, ''), ' ' order by pav.id_produkattributvalue) as kode_varian"), DB::raw("p.nama || ' ' || string_agg(coalesce(av.nama, ''), ' ' order by pav.id_produkattributvalue) as varian")])
        ->leftJoin('toko_griyanaura.ms_produk as p', 'p.id_produk', 'toko_griyanaura.ms_produkvarian.id_produk')
        ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
        ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
        ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
        ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
        ->groupBy('p.nama', 'toko_griyanaura.ms_produkvarian.kode_produkvarian');
        $query = $this->setTable(DB::raw("({$subQuery->toSql()}) as x"))
            ->mergeBindings($subQuery->getQuery());
        return $query;
    }

    public function produkVarianHarga() {
        return $this->hasMany(ProdukVarianHarga::class, 'kode_produkvarian', 'kode_produkvarian')->join(DB::raw("(select id_produkharga, id_varianharga from toko_griyanaura.ms_produkharga) as ph"), 'toko_griyanaura.ms_produkvarianharga.id_produkharga', 'ph.id_produkharga');
    }

    public function produkPersediaan() {
        return $this->hasMany(ProdukPersediaan::class, 'kode_produkvarian', 'kode_produkvarian')->join(DB::raw("(select id_gudang, nama as nama_gudang from toko_griyanaura.lv_gudang) as gdg"), 'toko_griyanaura.ms_produkpersediaan.id_gudang', 'gdg.id_gudang');
    }

    public function pindahGudangDetail() {
        return $this->hasOne(PindahGudangDetail::class, 'kode_produkvarian', 'kode_produkvarian');
    }

    public function produk() {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}
