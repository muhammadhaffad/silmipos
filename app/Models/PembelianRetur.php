<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PembelianRetur extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.tr_pembelianretur';
    protected $primaryKey = 'id_pembelianretur';
    protected $guarded = [];

    public function pembelianReturDetail() 
    {
        return $this->hasMany(PembelianReturDetail::class, 'id_pembelianretur', 'id_pembelianretur');
    }
    public function kontak()
    {
        return $this->hasOne(Kontak::class, 'id_kontak', 'id_kontak');
    }
}