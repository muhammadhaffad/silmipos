<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProdukPersediaan extends Model
{
    use HasFactory;
    use Compoships;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_persediaan';
    protected $table = 'toko_griyanaura.ms_produkpersediaan';
    protected $guarded = [];
    protected $hidden = ['inserted_at', 'updated_at', 'inserted_by', 'updated_by'];

    public function produkVarianHarga() {
        return $this->hasOne(ProdukVarianHarga::class, 'id_produkvarianharga', 'default_varianharga');
    }

    public function produkVarian() {
        return $this->belongsTo(ProdukVarian::class, 'kode_produkvarian', 'kode_produkvarian')
            ->select(['toko_griyanaura.ms_produkvarian.*', DB::raw("string_agg(coalesce(av.nama,''), '/' order by pav.id_produkattributvalue) as varian"), DB::raw("json_agg(json_build_object(coalesce(pav.id_produkattribut,0),coalesce(pav.id_attributvalue,0)) order by pav.id_produkattributvalue) as varian_id")])
            ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
            ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
            ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
            ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
            ->groupBy('toko_griyanaura.ms_produkvarian.kode_produkvarian');
    }

    public function gudang() {
        return $this->hasOne(Gudang::class, 'id_gudang', 'id_gudang');
    }
}
