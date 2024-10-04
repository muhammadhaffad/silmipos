<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianPembayaran extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_pembelianpembayaran';
    protected $primaryKey = 'id_pembelianpembayaran';
    protected $guarded = [];

    public function pembelianAlokasiPembayaran() 
    {
        return $this->hasMany(PembelianAlokasiPembayaran::class, 'id_pembelianpembayaran', 'id_pembelianpembayaran');
    }
}