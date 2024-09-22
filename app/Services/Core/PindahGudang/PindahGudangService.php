<?php

namespace App\Services\Core\PindahGudang;

use App\Models\PindahGudang;
use App\Models\PindahGudangDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PindahGudangService
{
    use JurnalService;

    public function storePindahGudang($request)
    {
        $rules = [
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required',
            'from_gudang' => 'required|numeric',
            'to_gudang' => 'required|numeric',
            'keterangan' => 'nullable|string',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $data = [
                'tanggal' => $request['tanggal'],
                'from_gudang' => $request['from_gudang'],
                'to_gudang' => $request['to_gudang'],
                'keterangan' => $request['keterangan'],
                'catatan' => $request['catatan'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ];
            if ($request['transaksi_no']) {
                $data['transaksi_no'] = $request['transaksi_no'];
            }
            $pindahGudang = PindahGudang::create($data);
            $transaksi = Transaksi::create([
                'tanggal' => $request['tanggal'],
                'transaksi_no' => $pindahGudang->transaksi_no,
                'id_transaksijenis' => 'persediaan_pindahgudang',
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $pindahGudang->update([
                'id_transaksi' => $transaksi->id_transaksi
            ]);
            $pindahGudang->refresh();
            DB::commit();
            return $pindahGudang;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function storePindahGudangDetail($idPindahGudang, $request)
    {
        $rules = [
            'harga_modal_dari_gudang' => 'required|numeric',
            'jumlah' => 'required|numeric|min:1',
            'harga_modal_ke_gudang' => 'required|numeric',
            'kode_produkvarian' => 'required'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();

        DB::beginTransaction();
        $dataPindahGudang = PindahGudang::where('id_pindahgudang', $idPindahGudang)->join(DB::raw('(select nama as nama_fromgudang, default_varianharga as varianharga_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg'), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')->join(DB::raw('(select nama as nama_togudang, default_varianharga as varianharga_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2'), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang')->first();
        $defaultKeterangan = "Pindah Gudang {$dataPindahGudang->nama_fromgudang} ke {$dataPindahGudang->nama_togudang}";
        if ($dataPindahGudang->is_batal) {
            abort(403);
        }
        try {
            /* tambah pindah gudang detail */
            $pindahGudangDetail = PindahGudangDetail::create([
                'kode_produkvarian' => $request['kode_produkvarian'],
                'id_pindahgudang' => $dataPindahGudang->id_pindahgudang,
                'jumlah' => $request['jumlah'],
                'harga_modal_dari_gudang' => $request['harga_modal_dari_gudang'],
                'harga_modal_ke_gudang' => $request['harga_modal_ke_gudang'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            /* perbarui stok persediaan */
            $dataDariPersediaan = ProdukPersediaan::where('kode_produkvarian', $request['kode_produkvarian'])->where('id_gudang', $dataPindahGudang->from_gudang)?->first();
            $dataKePersediaan = ProdukPersediaan::where('kode_produkvarian', $request['kode_produkvarian'])->where('id_gudang', $dataPindahGudang->to_gudang)?->first();
            if ($dataDariPersediaan) {
                $dataDariPersediaan->update([
                    'stok' => DB::raw('stok - ' . (int)$request['jumlah'])
                ]);
                if (!$dataDariPersediaan->default_varianharga) {
                    if (ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_fromgudang)->first()) {
                        $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_fromgudang)->first()->id_produkvarianharga;
                    } else {
                        $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                    }
                    $dataDariPersediaan->update([
                        'default_varianharga' => $defaultVarianHarga
                    ]);
                }
            } else {
                if (ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_fromgudang)->first()) {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_fromgudang)->first()->id_produkvarianharga;
                } else {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                }
                $dataDariPersediaan = ProdukPersediaan::craete([
                    'id_gudang' => $dataPindahGudang->from_gudang,
                    'kode_produkvarian' => $request['kode_produkvarian'],
                    'stok' => 0 - $request['jumlah'],
                    'default_varianharga' => $defaultVarianHarga
                ]);
            }
            if ($dataKePersediaan) {
                $dataKePersediaan->update([
                    'stok' => DB::raw('stok + ' . (int)$request['jumlah'])
                ]);
                if (!$dataKePersediaan->default_varianharga) {
                    if (ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_togudang)->first()) {
                        $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_togudang)->first()->id_produkvarianharga;
                    } else {
                        $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                    }
                    $dataKePersediaan->update([
                        'default_varianharga' => $defaultVarianHarga
                    ]);
                }
            } else {
                if (ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_togudang)->first()) {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->varianharga_togudang)->first()->id_produkvarianharga;
                } else {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                }
                $dataKePersediaan = ProdukPersediaan::create([
                    'id_gudang' => $dataPindahGudang->to_gudang,
                    'kode_produkvarian' => $request['kode_produkvarian'],
                    'stok' => 0 + $request['jumlah'],
                    'default_varianharga' => $defaultVarianHarga
                ]);
            }

            $dataDariPersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $dataDariPersediaan->id_persediaan,
                'tanggal' => $dataPindahGudang->tanggal,
                'keterangan' => "#{$dataPindahGudang->transaksi_no} Pindah Gudang ke " . $dataPindahGudang->nama_togudang,
                'stok_out' => $request['jumlah'],
                'hargabeli' => (int)$request['harga_modal_dari_gudang']
            ]);
            $dataKePersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $dataKePersediaan->id_persediaan,
                'tanggal' => $dataPindahGudang->tanggal,
                'keterangan' => "#{$dataPindahGudang->transaksi_no} Pindah Gudang dari " . $dataPindahGudang->nama_fromgudang,
                'stok_in' => $request['jumlah'],
                'hargabeli' => (int)$request['harga_modal_ke_gudang']
            ]);

            if ($request['harga_modal_dari_gudang'] > $request['harga_modal_ke_gudang']) {
                $detailTransaksi = [
                    [
                        'kode_akun' => '1301',
                        'keterangan' => $dataPindahGudang->keterangan ?: $defaultKeterangan,
                        'nominaldebit' => (int)($request['jumlah'] * $request['harga_modal_ke_gudang']),
                        'nominalkredit' => 0
                    ],
                    [
                        'kode_akun' => '1301',
                        'keterangan' => $dataPindahGudang->keterangan ?: $defaultKeterangan,
                        'nominaldebit' => 0,
                        'nominalkredit' => (int)($request['jumlah'] * $request['harga_modal_dari_gudang']),
                    ],
                    [
                        'kode_akun' => '4006',
                        'keterangan' => $dataPindahGudang->keterangan ?: $defaultKeterangan,
                        'nominaldebit' => (int)($request['jumlah'] * ($request['harga_modal_dari_gudang'] - $request['harga_modal_ke_gudang'])),
                        'nominalkredit' => 0,
                    ],
                ];
            } else if ($request['harga_modal_dari_gudang'] < $request['harga_modal_ke_gudang']) {
                $detailTransaksi = [
                    [
                        'kode_akun' => '1301',
                        'keterangan' => $dataPindahGudang->keterangan ?: $defaultKeterangan,
                        'nominaldebit' => (int)($request['jumlah'] * $request['harga_modal_ke_gudang']),
                        'nominalkredit' => 0
                    ],
                    [
                        'kode_akun' => '1301',
                        'keterangan' => $dataPindahGudang->keterangan ?: $defaultKeterangan,
                        'nominaldebit' => 0,
                        'nominalkredit' => (int)($request['jumlah'] * $request['harga_modal_dari_gudang']),
                    ],
                    [
                        'kode_akun' => '6202',
                        'keterangan' => $dataPindahGudang->keterangan ?: $defaultKeterangan,
                        'nominaldebit' => 0,
                        'nominalkredit' => (int)($request['jumlah'] * ($request['harga_modal_ke_gudang'] - $request['harga_modal_dari_gudang'])),
                    ],
                ];
            }
            if (isset($detailTransaksi)) {
                $this->entryJurnal($dataPindahGudang->id_transaksi, $detailTransaksi);
            }
            $dataDariPersediaan->refresh();
            $dataKePersediaan->refresh();
            DB::commit();
            return [
                'stok_dari_gudang' => (fmod($dataDariPersediaan->stok, 1) !== 0.00) ? $dataDariPersediaan->stok : (int)$dataDariPersediaan->stok,
                'stok_ke_gudang' => (fmod($dataKePersediaan->stok, 1) !== 0.00) ? $dataKePersediaan->stok : (int)$dataKePersediaan->stok
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deletePindahGudang($idPindahGudang)
    {
        DB::beginTransaction();
        try {
            $pindahGudang = PindahGudang::where('id_pindahgudang', $idPindahGudang)->first();
            if ($pindahGudang->is_batal) {
                abort(403);
            }
            $pindahGudangDetail = PindahGudangDetail::where('id_pindahgudang', $idPindahGudang)->get();
            foreach ($pindahGudangDetail as $item) {
                $persediaanDariGudang = ProdukPersediaan::where('kode_produkvarian', $item['kode_produkvarian'])
                                ->where('id_gudang', $pindahGudang->from_gudang)->first();
                $persediaanKeGudang = ProdukPersediaan::where('kode_produkvarian', $item['kode_produkvarian'])
                                ->where('id_gudang', $pindahGudang->to_gudang)->first();
                if ($persediaanDariGudang->stok + $item['jumlah'] < 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'stok' => 'Stok tidak boleh minus'
                    ]);
                }
                if ($persediaanKeGudang->stok - $item['jumlah'] < 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'stok' => 'Stok tidak boleh minus'
                    ]);
                }
                $persediaanDariGudang->update([
                    'stok' => DB::raw('stok + ' . $item['jumlah'])
                ]);
                $persediaanKeGudang->update([
                    'stok' => DB::raw('stok - ' . $item['jumlah'])
                ]);
                $dataDariPersediaanDetail = ProdukPersediaanDetail::create([
                    'id_persediaan' => $persediaanDariGudang->id_persediaan,
                    'tanggal' => $pindahGudang->tanggal,
                    'keterangan' => "#{$pindahGudang->transaksi_no} Batal Pindah Gudang ke " . $pindahGudang->nama_togudang,
                    'stok_in' => $item['jumlah'],
                    'hargabeli' => (int)$item['harga_modal_dari_gudang'] ?: 0
                ]);
                $dataKePersediaanDetail = ProdukPersediaanDetail::create([
                    'id_persediaan' => $persediaanKeGudang->id_persediaan,
                    'tanggal' => $pindahGudang->tanggal,
                    'keterangan' => "#{$pindahGudang->transaksi_no} Batal Pindah Gudang dari " . $pindahGudang->nama_fromgudang,
                    'stok_out' => $item['jumlah'],
                    'hargabeli' => (int)$item['harga_modal_ke_gudang'] ?: 0
                ]);
            }
            $pindahGudang->update([
                'is_batal' => true
            ]);
            DB::table('toko_griyanaura.tr_jurnal')->where('id_transaksi', $pindahGudang->id_transaksi)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
