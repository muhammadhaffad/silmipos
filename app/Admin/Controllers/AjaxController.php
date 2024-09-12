<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Akun;
use App\Models\Dynamic;
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
}
