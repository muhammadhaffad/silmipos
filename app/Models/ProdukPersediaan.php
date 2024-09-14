<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukPersediaan extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_persediaan';
    protected $table = 'toko_griyanaura.ms_produkpersediaan';
    protected $guarded = [];

    public function produkVarianHarga() {
        return $this->hasOne(ProdukVarianHarga::class, 'id_produkvarianharga', 'default_varianharga');
    }
}
