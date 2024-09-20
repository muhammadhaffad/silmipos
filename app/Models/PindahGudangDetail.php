<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PindahGudangDetail extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_pindahgudangdetail';
    protected $table = 'toko_griyanaura.tr_pindahgudangdetail';
    protected $guarded = [];
}
