<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Produk extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_produk';
    protected $table = 'toko_griyanaura.ms_produk';
    protected $guarded = [];
    protected $casts = ['in_stok' => 'string'];
    protected $hidden = ['inserted_at', 'updated_at', 'inserted_by', 'updated_by', 'deskripsi'];

    public function produkAttribut()
    {
        return $this->hasMany(ProdukAttribut::class, 'id_produk', 'id_produk')
            ->select('toko_griyanaura.ms_produkattribut.*', 'att.nama', DB::raw("string_agg(attval.nama, ',' order by attval.id_attributvalue) as varian"))
            ->join(DB::raw('(select id_produkattribut, id_attributvalue from "toko_griyanaura"."ms_produkattributvarian" group by id_produkattribut, id_attributvalue) as pav'), 'toko_griyanaura.ms_produkattribut.id_produkattribut', 'pav.id_produkattribut')
            ->join('toko_griyanaura.lv_attribut as att', 'toko_griyanaura.ms_produkattribut.id_attribut', 'att.id_attribut')
            ->join('toko_griyanaura.lv_attributvalue as attval', 'pav.id_attributvalue', 'attval.id_attributvalue')
            ->groupBy('toko_griyanaura.ms_produkattribut.id_produkattribut', 'att.nama')
            ->orderBy('id_attribut');
    }

    public function produkVarian()
    {
        return $this->hasMany(ProdukVarian::class, 'id_produk', 'id_produk')
            ->select(['toko_griyanaura.ms_produkvarian.*', DB::raw("string_agg(coalesce(av.nama,''), '/' order by pav.id_produkattributvalue) as varian"), DB::raw("json_agg(json_build_object(coalesce(pav.id_produkattribut,0),coalesce(pav.id_attributvalue,0)) order by pav.id_produkattributvalue) as varian_id")])
            ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
            ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
            ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
            ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
            ->groupBy('toko_griyanaura.ms_produkvarian.kode_produkvarian');
    }

    public function produkHarga()
    {
        return $this->hasMany(ProdukHarga::class, 'id_produk', 'id_produk')->join(DB::raw("(select id_varianharga, nama from toko_griyanaura.lv_varianharga) as vh"), 'toko_griyanaura.ms_produkharga.id_varianharga', 'vh.id_varianharga');
    }
}
