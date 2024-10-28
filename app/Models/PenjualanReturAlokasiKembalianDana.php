<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanReturAlokasiKembalianDana extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualanreturalokasikembaliandana';
    protected $primaryKey = 'id_penjualanreturalokasikembaliandana';
    protected $guarded = [];

    public function penjualanPembayaran() 
    {
        return $this->hasOne(PenjualanPembayaran::class,  'id_penjualanpembayaran', 'id_penjualanpembayaran');
    }
}