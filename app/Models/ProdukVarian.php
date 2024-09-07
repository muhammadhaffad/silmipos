<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukVarian extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'kode_produkvarian';
    protected $keyType = 'string';
    protected $table = 'toko_griyanaura.ms_produkvarian';
    protected $guarded = [];
    public $incrementing = false;
}
