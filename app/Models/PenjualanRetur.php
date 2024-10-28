<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenjualanRetur extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualanretur';
    protected $primaryKey = 'id_penjualanretur';
    protected $guarded = [];

    public function penjualanReturDetail() 
    {
        return $this->hasMany(PenjualanReturDetail::class, 'id_penjualanretur', 'id_penjualanretur');
    }
    public function kontak()
    {
        return $this->hasOne(Kontak::class, 'id_kontak', 'id_kontak');
    }
    public function penjualan()
    {
        return $this->hasOne(Penjualan::class, 'id_penjualan', 'id_penjualan');
    }
    public function penjualanDetail()
    {
        return $this->hasMany(PenjualanDetail::class, 'id_penjualan', 'id_penjualan');
    }
    public function penjualanReturAlokasiKembalianDana()
    {
        return $this->hasMany(PenjualanReturAlokasiKembalianDana::class, 'id_penjualanretur', 'id_penjualanretur');
    }
}