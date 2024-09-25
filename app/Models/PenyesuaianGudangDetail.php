<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenyesuaianGudangDetail extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_penyesuaiangudangdetail';
    protected $table = 'toko_griyanaura.tr_penyesuaiangudangdetail';
    protected $guarded = [];
}
