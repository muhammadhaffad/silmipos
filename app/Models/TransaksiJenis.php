<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiJenis extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $table = 'toko_griyanaura.lv_transaksijenis';
    protected $primaryKey = 'id_transaksijenis';
    protected $guarded = [];
    protected $keyType = 'string';
}
