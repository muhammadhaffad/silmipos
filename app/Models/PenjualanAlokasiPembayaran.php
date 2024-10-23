<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanAlokasiPembayaran extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualanalokasipembayaran';
    protected $primaryKey = 'id_penjualanalokasipembayaran';
    protected $guarded = [];

    public function penjualan() 
    {
        return $this->hasOne(Penjualan::class, 'id_penjualan', 'id_penjualaninvoice');
    }
}