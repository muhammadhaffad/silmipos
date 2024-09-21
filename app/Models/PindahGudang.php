<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PindahGudang extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_pindahgudang';
    protected $table = 'toko_griyanaura.tr_pindahgudang';
    protected $guarded = [];

    public function pindahGudangDetail() {
        return $this->hasMany(PindahGudangDetail::class, 'id_pindahgudang', 'id_pindahgudang');
    }
}
