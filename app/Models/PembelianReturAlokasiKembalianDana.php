<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianReturAlokasiKembalianDana extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_pembelianreturalokasikembaliandana';
    protected $primaryKey = 'id_pembelianreturalokasikembaliandana';
    protected $guarded = [];
}