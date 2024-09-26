<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenyesuaianGudang extends Model
{
    use HasFactory;
    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';
    protected $primaryKey = 'id_penyesuaiangudang';
    protected $table = 'toko_griyanaura.tr_penyesuaiangudang';
    protected $guarded = [];

    public function penyesuaianGudangDetail() {
        return $this->hasMany(PenyesuaianGudangDetail::class, 'id_penyesuaiangudang', 'id_penyesuaiangudang');
    }
}
