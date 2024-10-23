<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanPembayaran extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualanpembayaran';
    protected $primaryKey = 'id_penjualanpembayaran';
    protected $guarded = [];

    public function penjualanAlokasiPembayaran() 
    {
        return $this->hasMany(PenjualanAlokasiPembayaran::class, 'id_penjualanpembayaran', 'id_penjualanpembayaran');
    }
    public function kontak()
    {
        return $this->hasOne(Kontak::class, 'id_kontak', 'id_kontak');
    }
}