<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_jurnal';
    protected $primaryKey = 'id_jurnal';
    protected $guarded = [];

    public function akun() {
        return $this->hasOne(Akun::class, 'kode_akun', 'kode_akun');
    }

    public function transaksi() {
        return $this->hasOne(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }
}
