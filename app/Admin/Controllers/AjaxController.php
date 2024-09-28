<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Akun;
use App\Models\Dynamic;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AjaxController extends Controller
{
    public function akun(Request $request) {
        $q = $request->get('q');
        if ($request->get('id')) {
            return Akun::where('kode_akun', '=', $request->get('id'))->first(['kode_akun as id', DB::raw("kode_akun || ' - ' || nama as text")]);    
        }
        return Akun::where(DB::raw('kode_akun || nama'), 'ilike', "%$q%")->paginate(null, ['kode_akun as id', DB::raw("kode_akun || ' - ' || nama as text")]);
    }

    public function attributValue(Request $request, $idAttribut = null) {
        $q = $request->get('q');
        if (!$idAttribut) {
            $idAttribut = DB::raw('id_attribut');
        }
        if ($request->get('id')) {
            return (new Dynamic())->setTable('toko_griyanaura.lv_attributvalue')->where('id_attribut', $idAttribut)->where('id_attributvalue', '=', $request->get('id'))->first(['id_attributvalue as id', "nama as text"]);    
        }
        return (new Dynamic())->setTable('toko_griyanaura.lv_attributvalue')->where('id_attribut', $idAttribut)->where(DB::raw('nama'), 'ilike', "%$q%")->paginate(null, ['id_attributvalue as id', "nama as text"]);
    }
    public function getVarians(Request $request) {
        $q = $request->get('q');
        if ($request->get('id')) {
            return (new Dynamic())->setTable('toko_griyanaura.lv_attributvalue as attval')->join('toko_griyanaura.lv_attribut as att', 'att.id_attribut', 'attval.id_attribut')->where(DB::raw('attval.id_attributvalue'), $request->get('id'))->first(['id_attributvalue as id', DB::raw("'<b>' || att.nama || '</b> : ' || attval.nama as text")]);    
        }
        return (new Dynamic())->setTable('toko_griyanaura.lv_attributvalue as attval')->join('toko_griyanaura.lv_attribut as att', 'att.id_attribut', 'attval.id_attribut')->where(DB::raw('attval.nama'), 'ilike', "%$q%")->paginate(null, ['id_attributvalue as id', DB::raw("'<b>' || att.nama || '</b> : ' || attval.nama as text")]);
    }
    public function getProduk(Request $request) {
        $q = $request->get('q');
        $subQuery = ProdukVarian::select(['toko_griyanaura.ms_produkvarian.kode_produkvarian as id', DB::raw("toko_griyanaura.ms_produkvarian.kode_produkvarian || ' - ' || p.nama || ' ' || string_agg(av.nama, ' ' order by pav.id_produkattributvalue) as text")])
            ->leftJoin('toko_griyanaura.ms_produk as p', 'p.id_produk', 'toko_griyanaura.ms_produkvarian.id_produk')
            ->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
            ->leftJoin('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')
            ->leftJoin('toko_griyanaura.ms_produkattribut as at', 'at.id_produkattribut', 'pav.id_produkattribut')
            ->leftJoin('toko_griyanaura.lv_attribut as a', 'a.id_attribut', 'at.id_attribut')
            ->groupBy('p.nama', 'toko_griyanaura.ms_produkvarian.kode_produkvarian');
        $query = (new Dynamic)->setTable(DB::raw("({$subQuery->toSql()}) as x"))
            ->mergeBindings($subQuery->getQuery());
        if ($request->get('id')) {
            return $query->where('kode_produkvarian', $request->get('id'))->first();
        } 
        return $query->where(DB::raw("x.id || ' ' || x.text"), 'ilike', "%$q%")->paginate();
    }
}
