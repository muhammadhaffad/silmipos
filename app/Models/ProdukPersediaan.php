<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->belongsTo(ProdukVarian::class, 'kode_produkvarian', 'kode_produkvarian');
    }
}
