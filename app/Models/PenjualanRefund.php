<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanRefund extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualanrefund';
    protected $primaryKey = 'id_penjualanrefund';
    protected $guarded = [];

    public function penjualanRefundDetail() 
    {
        return $this->hasMany(PenjualanRefundDetail::class, 'id_penjualanrefund', 'id_penjualanrefund');
    }
    public function kontak()
    {
        return $this->hasOne(Kontak::class, 'id_kontak', 'id_kontak');
    }
}