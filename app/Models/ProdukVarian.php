<?php

namespace App\Models;

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
    public $incrementing = false;

    public function produkVarianHarga() {
        return $this->hasMany(ProdukVarianHarga::class, 'kode_produkvarian', 'kode_produkvarian')->join(DB::raw("(select id_produkharga, id_varianharga from toko_griyanaura.ms_produkharga) as ph"), 'toko_griyanaura.ms_produkvarianharga.id_produkharga', 'ph.id_produkharga');
    }

    public function produkPersediaan() {
        return $this->hasMany(ProdukPersediaan::class, 'kode_produkvarian', 'kode_produkvarian')->join(DB::raw("(select id_gudang, nama as nama_gudang from toko_griyanaura.lv_gudang) as gdg"), 'toko_griyanaura.ms_produkpersediaan.id_gudang', 'gdg.id_gudang');
    }

    public function pindahGudangDetail() {
        return $this->hasOne(PindahGudangDetail::class, 'kode_produkvarian', 'kode_produkvarian');
    }
}
