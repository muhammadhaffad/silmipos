<?php

namespace App\Services\Core\PenyesuaianGudang;

use App\Models\PenyesuaianGudang;
use App\Models\PenyesuaianGudangDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenyesuaianGudangService
{
    public function storePenyesuaianGudang($request)
    {
        $rules = [
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required',
            'keterangan' => 'nullable|string',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $data = [
                'tanggal' => $request['tanggal'],
                'keterangan' => $request['keterangan'],
                'catatan' => $request['catatan'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ];
            if ($request['transaksi_no']) {
                $data['transaksi_no'] = $request['transaksi_no'];
            }
            $penyesuaianGudang = PenyesuaianGudang::create($data);
            $transaksi = Transaksi::create([
                'tanggal' => $request['tanggal'],
                'transaksi_no' => $penyesuaianGudang->transaksi_no,
                'id_transaksijenis' => 'persediaan_penyesuaian',
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $penyesuaianGudang->update([
                'id_transaksi' => $transaksi->id_transaksi
            ]);
            $penyesuaianGudang->refresh();
            DB::commit();
            return $penyesuaianGudang;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function storePenyesuaianGudangDetail($request)
    {
        $rules = [
            'id_gudang' => 'required|numeric',
            'id_penyesuaiangudang' => 'required|numeric',
            'id_penyesuaiangudangdetail' => 'nullable|numeric',
            'hargamodal' => 'required|numeric',
            'kode_produkvarian' => 'required|string',
            'jumlah_penyesuaian' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();

        DB::beginTransaction();
        try {
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $request['kode_produkvarian'])->where('id_gudang', $request['id_gudang'])->first();
            if (!$persediaanProduk) {
                if (ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $request['id_gudang'])->first()) {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $request['id_gudang'])->first()->id_produkvarianharga;
                } else {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                }
                $persediaanProduk = ProdukPersediaan::create([
                    'id_gudang' => $request['id_gudang'],
                    'kode_produkvarian' => $request['kode_produkvarian'],
                    'stok' => 0,
                    'default_varianharga' => $defaultVarianHarga
                ]);
            }
            if ($request['id_penyesuaiangudangdetail']) {
                if ($request['jumlah_penyesuaian']) {
                    $penyesuaianGudangDetail = PenyesuaianGudangDetail::where('id_penyesuaiangudangdetail', $request['id_penyesuaiangudangdetail'])->first();
                    $penyesuaianGudangDetail->update([
                        'jumlah' => $request['jumlah_penyesuaian'],
                        'selisih' => $request['jumlah_penyesuaian'] - $persediaanProduk->stok,
                        'harga_modal' => $request['hargamodal']
                    ]);
                    $penyesuaianGudangDetail->refresh();
                } else {
                    PenyesuaianGudangDetail::where('id_penyesuaiangudangdetail', $request['id_penyesuaiangudangdetail'])->delete();
                    $penyesuaianGudangDetail = null;
                }
            } else {
                if ($request['jumlah_penyesuaian']) {
                    $penyesuaianGudangDetail = PenyesuaianGudangDetail::create([
                        'id_gudang' => $request['id_gudang'],
                        'kode_produkvarian' => $request['kode_produkvarian'],
                        'id_penyesuaiangudang' => $request['id_penyesuaiangudang'],
                        'id_gudang' => $request['id_gudang'],
                        'harga_modal' => $request['hargamodal'],
                        'jumlah' => $request['jumlah_penyesuaian'],
                        'selisih' => $request['jumlah_penyesuaian'] - $persediaanProduk->stok
                    ]);
                }
            }
            DB::commit();
            return $penyesuaianGudangDetail;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function validPenyesuaianGudang($idPenyesuaianGudang, $request)
    {
        $rules = [
            'is_valid' => 'required'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $penyesuaianGudang = PenyesuaianGudang::where('id_penyesuaiangudang', $idPenyesuaianGudang)->first();
            $penyesuaianGudang->update([
                'is_valid' => $request['is_valid']
            ]);
            $penyesuaianGudangDetail = PenyesuaianGudangDetail::where('id_penyesuaiangudang', $idPenyesuaianGudang)->get();
            foreach ($penyesuaianGudangDetail as $produk) {
                ProdukPersediaan::where('kode_produkvarian', $produk->kode_produkvarian)->where('id_gudang', $produk->id_gudang)->update([
                    'stok' => DB::raw('stok +' . $produk->selisih)
                ]);
                if ($produk->selisih > 0) {
                    $dataPersediaanDetail = ProdukPersediaanDetail::create([
                        'id_persediaan' => $dataDariPersediaan->id_persediaan,
                        'tanggal' => $dataPindahGudang->tanggal,
                        'keterangan' => "#{$dataPindahGudang->transaksi_no} Pindah Gudang ke " . $dataPindahGudang->nama_togudang,
                        'stok_out' => $request['jumlah'],
                        'hargabeli' => (int)$request['harga_modal_dari_gudang']
                    ]);
                } else if ($produk->selisih < 0) {

                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
