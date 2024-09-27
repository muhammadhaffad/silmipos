<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pembelian extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_pembelian';
    protected $primaryKey = 'id_pembelian';
    protected $guarded = [];

    public function pembelianDetail() {
        return $this->hasMany(PembelianDetail::class, 'id_pembelian', 'id_pembelian');
    }
}
