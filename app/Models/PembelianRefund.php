<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianRefund extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_pembelianrefund';
    protected $primaryKey = 'id_pembelianrefund';
    protected $guarded = [];

    public function pembelianRefundDetail() 
    {
        return $this->hasMany(PembelianRefundDetail::class, 'id_pembelianrefund', 'id_pembelianrefund');
    }
    public function kontak()
    {
        return $this->hasOne(Kontak::class, 'id_kontak', 'id_kontak');
    }
}