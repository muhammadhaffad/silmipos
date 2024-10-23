<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Akun;
use App\Models\Dynamic;
use App\Models\Pembelian;
use App\Models\PembelianPembayaran;
use App\Models\Penjualan;
use App\Models\PenjualanPembayaran;
use App\Models\Produk;
use App\Models\ProdukPersediaan;
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
    public function getKontakSupplier(Request $request) {
        $q = $request->get('q');
        if ($request->get('id')) {
            return (new Dynamic())->setTable('toko_griyanaura.ms_kontak')->where('jenis_kontak', 'supplier')->where('id_kontak', $request->get('id'))->first(['id_kontak as id', DB::raw("nama || ' - ' || alamat as text")]);
        }
        return (new Dynamic())->setTable('toko_griyanaura.ms_kontak')->where('jenis_kontak', 'supplier')->where('nama', 'ilike', "%$q%")->paginate(null, ['id_kontak as id', DB::raw("nama || ' - ' || alamat as text")]);
    }
    public function getKontakCustomer(Request $request) {
        $q = $request->get('q');
        if ($request->get('id')) {
            return (new Dynamic())->setTable('toko_griyanaura.ms_kontak')->where('jenis_kontak', 'customer')->where('id_kontak', $request->get('id'))->first(['id_kontak as id', DB::raw("nama || ' - ' || alamat as text")]);
        }
        return (new Dynamic())->setTable('toko_griyanaura.ms_kontak')->where('jenis_kontak', 'customer')->where('nama', 'ilike', "%$q%")->paginate(null, ['id_kontak as id', DB::raw("nama || ' - ' || alamat as text")]);
    }
    public function getProduk(Request $request) {
        $q = $request->get('q');
        $subQuery = ProdukVarian::select(['toko_griyanaura.ms_produkvarian.kode_produkvarian as kode_produkvarian', DB::raw("toko_griyanaura.ms_produkvarian.kode_produkvarian || ' - ' || p.nama || ' ' || string_agg(coalesce(av.nama, ''), ' ' order by pav.id_produkattributvalue) as text")])
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
        return $query->where(DB::raw("x.kode_produkvarian || ' ' || x.text"), 'ilike', "%$q%")->paginate();
    }
    public function getProdukDetail(Request $request) {
        $kode = $request->get('kode_produkvarian');
        $gudang = $request->get('id_gudang');
        $gudang = (new Dynamic())->setTable('toko_griyanaura.lv_gudang')->where('id_gudang', $gudang)->first();
        return ProdukVarian::with(['produk', 'produkVarianHarga' => function ($q) use ($gudang) {
                $q->where('id_varianharga', $gudang?->default_varianharga ?: 1);
            }, 'produkPersediaan' => function ($q) use ($gudang) {
                $q->with('produkVarianHarga')->where('gdg.id_gudang', $gudang?->id_gudang)->first();
            }])->where('kode_produkvarian', $kode)->first()->toArray();
    }
    public function getPembelian(Request $request)
    {
        $q = $request->get('q');
        $idSupplier = $request->get('id_supplier');
        if ($request->get('id')) {
            return Pembelian::select('id_pembelian as id', 'transaksi_no as text')->where('jenis', 'invoice')->where('id_kontak', $idSupplier)->where('id_pembelian', $request->get('id'))->first(['id_pembelian as id', DB::raw("transaksi_no as text")]);
        }
        return Pembelian::select('id_pembelian as id', 'transaksi_no as text')->where('id_kontak', $idSupplier)->where('jenis', 'invoice')->where('transaksi_no', 'ilike', "%$q%")->paginate(null, ['id_pembelian as id', DB::raw("transaksi_no as text")]);
    }
    public function getPembelianDetail(Request $request) {
        $id = $request->get('id_pembelian');
        $idSupplier = $request->get('id_supplier');
        return Pembelian::select('id_pembelian', 'transaksi_no', DB::raw("TO_CHAR(tanggaltempo, 'YYYY-MM-DD') as tanggaltempo"), 'grandtotal', DB::raw('toko_griyanaura.f_getsisatagihan(transaksi_no) as sisatagihan'))->where('id_kontak', $idSupplier)->where('id_pembelian', $id)->first()?->toArray() ?: [];
    }
    public function getPembelianPembayaran(Request $request)
    {
        $q = $request->get('q');
        $idSupplier = $request->get('id_supplier');
        if ($request->get('id')) {
            return PembelianPembayaran::select('id_pembelianpembayaran as id', 'transaksi_no as text')->where('jenisbayar', 'DP')->where('id_kontak', $idSupplier)->where('id_pembelianpembayaran', $request->get('id'))->first(['id_pembelianpembayaran as id', DB::raw("transaksi_no as text")]);
        }
        return PembelianPembayaran::select('id_pembelianpembayaran as id', 'transaksi_no as text')->where('id_kontak', $idSupplier)->where('jenisbayar', 'DP')->where('transaksi_no', 'ilike', "%$q%")->paginate(null, ['id_pembelianpembayaran as id', DB::raw("transaksi_no as text")]);
    }
    public function getPembelianPembayaranDetail(Request $request) {
        $id = $request->get('id_pembelianpembayaran');
        $idSupplier = $request->get('id_supplier');
        return PembelianPembayaran::select('id_pembelianpembayaran', 'transaksi_no', 'nominal', DB::raw('toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'))->where('id_kontak', $idSupplier)->where('jenisbayar', 'DP')->where('id_pembelianpembayaran', $id)->first()?->toArray() ?: [];
    }
    public function getPenjualan(Request $request)
    {
        $q = $request->get('q');
        $idCustomer = $request->get('id_customer');
        if ($request->get('id')) {
            return Penjualan::select('id_penjualan as id', 'transaksi_no as text')->where('jenis', 'invoice')->where('id_kontak', $idCustomer)->where('id_penjualan', $request->get('id'))->first(['id_penjualan as id', DB::raw("transaksi_no as text")]);
        }
        return Penjualan::select('id_penjualan as id', 'transaksi_no as text')->where('id_kontak', $idCustomer)->where('jenis', 'invoice')->where('transaksi_no', 'ilike', "%$q%")->paginate(null, ['id_penjualan as id', DB::raw("transaksi_no as text")]);
    }
    public function getPenjualanDetail(Request $request) {
        $id = $request->get('id_penjualan');
        $idCustomer = $request->get('id_customer');
        return Penjualan::select('id_penjualan', 'transaksi_no', DB::raw("TO_CHAR(tanggaltempo, 'YYYY-MM-DD') as tanggaltempo"), 'grandtotal', DB::raw('toko_griyanaura.f_getsisatagihan(transaksi_no) as sisatagihan'))->where('id_kontak', $idCustomer)->where('id_penjualan', $id)->first()?->toArray() ?: [];
    }
    public function getPenjualanPembayaran(Request $request)
    {
        $q = $request->get('q');
        $idCustomer = $request->get('id_customer');
        if ($request->get('id')) {
            return PenjualanPembayaran::select('id_penjualanpembayaran as id', 'transaksi_no as text')->where('jenisbayar', 'DP')->where('id_kontak', $idCustomer)->where('id_penjualanpembayaran', $request->get('id'))->first(['id_penjualanpembayaran as id', DB::raw("transaksi_no as text")]);
        }
        return PenjualanPembayaran::select('id_penjualanpembayaran as id', 'transaksi_no as text')->where('id_kontak', $idCustomer)->where('jenisbayar', 'DP')->where('transaksi_no', 'ilike', "%$q%")->paginate(null, ['id_penjualanpembayaran as id', DB::raw("transaksi_no as text")]);
    }
    public function getPenjualanPembayaranDetail(Request $request) {
        $id = $request->get('id_penjualanpembayaran');
        $idSupplier = $request->get('id_supplier');
        return PenjualanPembayaran::select('id_penjualanpembayaran', 'transaksi_no', 'nominal', DB::raw('toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'))->where('id_kontak', $idSupplier)->where('jenisbayar', 'DP')->where('id_penjualanpembayaran', $id)->first()?->toArray() ?: [];
    }
}
