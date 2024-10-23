<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penjualan extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_penjualan';
    protected $primaryKey = 'id_penjualan';
    protected $guarded = [];

    public function penjualanDetail() {
        return $this->hasMany(PenjualanDetail::class, 'id_penjualan', 'id_penjualan');
    }
    public function kontak() {
        return $this->hasOne(Kontak::class, 'id_kontak', 'id_kontak');
    }
    public function gudang() {
        return $this->hasOne(Gudang::class, 'id_gudang', 'id_gudang');
    }
    public function penjualanInvoice() {
        return $this->hasMany(Penjualan::class, 'penjualan_parent', 'id_penjualan')->where('jenis', 'invoice');
    }
    public function penjualanOrder() {
        return $this->belongsTo(Penjualan::class, 'penjualan_parent', 'id_penjualan')->where('jenis', 'order');
    }
}
