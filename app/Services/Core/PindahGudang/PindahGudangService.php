<?php
namespace App\Services\Core\PindahGudang;

use App\Models\PindahGudang;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PindahGudangService {
    use JurnalService;

    public function createPindahGudangDetail($idPindahGudang, $request) {
        $rules = [
            'harga_modal_dari_gudang' => 'required|numeric',
            'jumlah' => 'required|numeric',
            'harga_modal_ke_gudang' => 'required|numeric',
            'kode_produkvarian' => 'required'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        $dataPindahGudang = PindahGudang::find($idPindahGudang)
            ->join(DB::raw('(select nama as nama_fromgudang, default_varianharga as varianharga_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg'), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')
            ->join(DB::raw('(select nama as nama_togudang, default_varianharga as varianharga_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2'), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang');
        try {
            /* perbarui stok persediaan */
            $dataDariPersediaan = ProdukPersediaan::where('kode_produkvarian', $request['kode_produkvarian'])->where('id_gudang', $dataPindahGudang->from_gudang)?->first();
            $dataKePersediaan = ProdukPersediaan::where('kode_produkvarian', $request['kode_produkvarian'])->where('id_gudang', $dataPindahGudang->to_gudang)?->first();
            if ($dataDariPersediaan) {
                $dataDariPersediaan->update([
                    'stok' => DB::raw('stok - ' . (int)$request['jumlah'])
                ]);
                if (!$dataDariPersediaan->default_varianharga) {
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->from_gudang)->first()->id_produkvarianharga;        
                    $dataDariPersediaan->update([
                        'default_varianharga' => $defaultVarianHarga
                    ]);
                }
            } else {
                $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->from_gudang)->first()->id_produkvarianharga;
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
                    $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->to_gudang)->first()->id_produkvarianharga;        
                    $dataKePersediaan->update([
                        'default_varianharga' => $defaultVarianHarga
                    ]);
                }
            } else {
                $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $request['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $dataPindahGudang->to_gudang)->first()->id_produkvarianharga; 
                $dataDariPersediaan = ProdukPersediaan::craete([
                    'id_gudang' => $dataPindahGudang->from_gudang,
                    'kode_produkvarian' => $request['kode_produkvarian'],
                    'stok' => 0 + $request['jumlah'],
                    'default_varianharga' => $defaultVarianHarga
                ]);
            }

            $dataDariPersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $dataDariPersediaan->id_persediaan,
                'tanggal' => $dataPindahGudang->tanggal,
                'keterangan' => 'Pindah Gudang ke ' . $dataPindahGudang->nama_togudang,
                'stok_out' => $request['jumlah'],
                'hargabeli' => $request['harga_modal_dari_gudang']
            ]);
            $dataKePersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $dataKePersediaan->id_persediaan,
                'tanggal' => $dataPindahGudang->tanggal,
                'keterangan' => 'Pindah Gudang dari ' . $dataPindahGudang->nama_fromgudang,
                'stok_in' => $request['jumlah'],
                'hargabeli' => $request['harga_modal_ke_gudang']
            ]);

            $transaksi = Transaksi::create([
                'tanggal' => $request['tanggal'],
                'transaksi_no' => $dataPindahGudang->transaksi_no,
                'id_transaksijenis' => 'persediaan_pindahgudang',
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $detailTransaksi = [
                [
                    'kode_akun' => '1301',
                    'keterangan' => $dataPindahGudang->keterangan,
                    'nominalkredit' => $request['jumlah'] * $request['harga_modal_dari_gudang'],
                    'nominaldebit' => 0
                ],
                [
                    'kode_akun' => '6201',
                    'keterangan' => $dataPindahGudang->keterangan,
                    'nominalkredit' => 0,
                    'nominaldebit' => $request['jumlah'] * $request['harga_modal_dari_gudang']
                ],
                [
                    'kode_akun' => '6201',
                    'keterangan' => $dataPindahGudang->keterangan,
                    'nominalkredit' => $request['jumlah'] * $request['harga_modal_ke_gudang'],
                    'nominaldebit' => 0
                ],
                [
                    'kode_akun' => '1301',
                    'keterangan' => $dataPindahGudang->keterangan,
                    'nominalkredit' => 0,
                    'nominaldebit' => $request['jumlah'] * $request['harga_modal_ke_gudang']
                ],
            ];
            $this->entryJurnal($transaksi->id_transaksi, $detailTransaksi);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }
    }
}