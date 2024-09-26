<?php

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenyesuaianGudangDetail extends Model
{
    use  Compoships;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_penyesuaiangudangdetail';
    protected $table = 'toko_griyanaura.tr_penyesuaiangudangdetail';
    protected $guarded = [];

    public function produkPersediaan() {
        return $this->hasOne(ProdukPersediaan::class, ['kode_produkvarian', 'id_gudang'], ['kode_produkvarian', 'id_gudang']);
    }
}
