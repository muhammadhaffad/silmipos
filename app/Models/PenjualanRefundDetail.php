<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanRefundDetail extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualanrefunddetail';
    protected $primaryKey = 'id_penjualanrefunddetail';
    protected $guarded = [];

    public function penjualanPembayaranDP() 
    {
        return $this->hasOne(PenjualanPembayaran::class, 'id_penjualanpembayaran', 'id_penjualanpembayaran')->where('jenisbayar', 'DP');
    }
}