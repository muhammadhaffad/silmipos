<?php

namespace App\Services\Core\PenyesuaianGudang;

use App\Models\PenyesuaianGudang;
use App\Models\PenyesuaianGudangDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PenyesuaianGudangService
{
    use JurnalService;

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
            $penyesuaianGudang = PenyesuaianGudang::where('id_penyesuaiangudang', $request['id_penyesuaiangudang'])->first();
            if ($penyesuaianGudang->is_valid) {
                abort(403);
            }
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
                if ($request['jumlah_penyesuaian'] != null) {
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
                if ($request['jumlah_penyesuaian'] != null) {
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
    public function validatePenyesuaianGudang($idPenyesuaianGudang, $request)
    {
        $rules = [
            'is_valid' => 'required|in:on,off'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        $request['is_valid'] = $request['is_valid'] == 'on' ? true : false;
        DB::beginTransaction();
        try {
            $penyesuaianGudang = PenyesuaianGudang::where('id_penyesuaiangudang', $idPenyesuaianGudang)->first();
            if ($penyesuaianGudang->is_valid) {
                abort(403);
            }
            if (!$request['is_valid']) {
                DB::commit();
                return $penyesuaianGudang;
            }
            $penyesuaianGudang->update([
                'is_valid' => $request['is_valid']
            ]);
            $penyesuaianGudangDetail = PenyesuaianGudangDetail::with('produkPersediaan')->where('id_penyesuaiangudang', $idPenyesuaianGudang)->get();
            foreach ($penyesuaianGudangDetail as $produk) {
                ProdukPersediaan::where('kode_produkvarian', $produk->kode_produkvarian)->where('id_gudang', $produk->id_gudang)->update([
                    'stok' => DB::raw('stok +' . (int)$produk->selisih)
                ]);
                if ($produk->selisih > 0) {
                    $dataPersediaanDetail = ProdukPersediaanDetail::create([
                        'id_persediaan' => $produk->produkPersediaan->id_persediaan,
                        'tanggal' => $penyesuaianGudang->tanggal,
                        'keterangan' => "#{$penyesuaianGudang->transaksi_no} Penyesuaian stok",
                        'stok_in' => $produk->selisih,
                        'hargabeli' => $produk->harga_modal
                    ]);
                    $detailTransaksi = [
                        [
                            'kode_akun' => '1301',
                            'keterangan' => $penyesuaianGudang->keterangan ?: 'Penyesuaian stok ' . $produk->kode_produkvarian,
                            'nominaldebit' => (int)(abs($produk->selisih) * $produk->harga_modal),
                            'nominalkredit' => 0
                        ],
                        [
                            'kode_akun' => '4006',
                            'keterangan' => $penyesuaianGudang->keterangan ?: 'Penyesuaian stok ' . $produk->kode_produkvarian,
                            'nominaldebit' => 0,
                            'nominalkredit' => (int)(abs($produk->selisih) * $produk->harga_modal),
                        ]
                    ];
                } else if ($produk->selisih < 0) {
                    $dataPersediaanDetail = ProdukPersediaanDetail::create([
                        'id_persediaan' => $produk->produkPersediaan->id_persediaan,
                        'tanggal' => $penyesuaianGudang->tanggal,
                        'keterangan' => "#{$penyesuaianGudang->transaksi_no} Penyesuaian stok",
                        'stok_out' => $produk->selisih,
                        'hargabeli' => $produk->harga_modal
                    ]);
                    $detailTransaksi = [
                        [
                            'kode_akun' => '6202',
                            'keterangan' => $penyesuaianGudang->keterangan ?: 'Penyesuaian stok ' . $produk->kode_produkvarian,
                            'nominaldebit' => (int)(abs($produk->selisih) * $produk->harga_modal),
                            'nominalkredit' => 0
                        ],
                        [
                            'kode_akun' => '1301',
                            'keterangan' => $penyesuaianGudang->keterangan ?: 'Penyesuaian stok ' . $produk->kode_produkvarian,
                            'nominaldebit' => 0,
                            'nominalkredit' => (int)(abs($produk->selisih) * $produk->harga_modal),
                        ]
                    ];
                }
                if (isset($detailTransaksi)) {
                    $this->entryJurnal($penyesuaianGudang->id_transaksi, $detailTransaksi);
                }
            }
            DB::commit();
            return $penyesuaianGudang;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deletePenyesuaianGudang($idPenyesuaianGudang) {
        $penyesuaianGudang = (new PenyesuaianGudang())->where('id_penyesuaiangudang', $idPenyesuaianGudang)->first();
        if ($penyesuaianGudang->is_valid) {
            abort(403);
        }
        DB::beginTransaction();
        try {
            PenyesuaianGudangDetail::where('id_penyesuaiangudang', $idPenyesuaianGudang)->delete();
            PenyesuaianGudang::where('id_penyesuaiangudang', $idPenyesuaianGudang)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
