<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukPersediaanDetail extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_persediaandetail';
    protected $table = 'toko_griyanaura.ms_produkpersediaandetail';
    protected $guarded = [];
}
