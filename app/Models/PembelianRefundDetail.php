<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianRefundDetail extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_pembelianrefunddetail';
    protected $primaryKey = 'id_pembelianrefunddetail';
    protected $guarded = [];

    public function pembelianPembayaranDP() 
    {
        return $this->hasOne(PembelianPembayaran::class, 'id_pembelianpembayaran', 'id_pembelianpembayaran')->where('jenisbayar', 'DP');
    }
}