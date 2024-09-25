<?php

namespace App\Services\Core\PenyesuaianGudang;

use App\Models\PenyesuaianGudang;
use App\Models\PenyesuaianGudangDetail;
use App\Models\ProdukPersediaan;
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
            'jumlahpenyesuaian' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();

        DB::beginTransaction();
        try {
            $persediaanProduk = ProdukVarian::where('kode_produkvarian', $request['kode_produkvarian'])->where('id_gudang', $request['id_gudang'])->first();
            if ($persediaanProduk) {
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
                if ($request['jumlahpenyesuaian']) {
                    $penyesuaianGudangDetail = PenyesuaianGudangDetail::where('id_penyesuaiangudangdetail', $request['id_penyesuaiangudangdetail'])->update([
                        'jumlah' => $request['jumlah_penyesuaian'],
                        'selisih' => $request['jumlah_penyesuaian'] - $persediaanProduk->stok,
                        'harga_modal' => $request['hargamodal']
                    ]);
                } else {
                    PenyesuaianGudangDetail::where('id_penyesuaiangudangdetail', $request['id_penyesuaiangudangdetail'])->delete();
                }
            } else {
                if ($request['jumlahpenyesuaian']) {
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
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
