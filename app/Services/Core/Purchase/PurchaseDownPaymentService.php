<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchasePaymentException;
use App\Models\Pembelian;
use App\Models\PembelianAlokasiPembayaran;
use App\Models\PembelianPembayaran;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseDownPaymentService 
{
    use JurnalService;
    public function storePayment($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_kontak' => 'required|numeric',
            'catatan' => 'nullable|string',
            'totaldp' => 'required|numeric',
            'pembelianAlokasiPembayaran' => 'required|array',
            'pembelianAlokasiPembayaran.*.id_pembelian' => 'required|string',
            'pembelianAlokasiPembayaran.*.nominalbayar' => 'required|numeric',
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $noTransaksi = DB::select("select ('PDP' || lpad(nextval('toko_griyanaura.tr_pembelianpembayaran_dp_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'pembelianpembayaran_dp',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $payment = PembelianPembayaran::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'dariakun' => '1001',
                'jenisbayar' => 'DP',
                'tanggal' => $request['tanggal'],
                'catatan' => $request['catatan'],
                'nominal' => (int)$request['totaldp'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $total = 0;
            foreach ($request['pembelianAlokasiPembayaran'] as $item) {
                $total += $item['nominalbayar'];
            }
            if ($total > $request['totaldp']) {
                throw new PurchasePaymentException('Total pembayaran melebihi total DP');
            }
            foreach ($request['pembelianAlokasiPembayaran'] as $item) {
                $newData = [
                    'nominalbayar' => $item['nominalbayar'],
                    'id_pembelian' => $item['id_pembelian']
                ];
                $this->storeAllocatePaymentToInvoice($payment, $newData);
            }
            $payment = PembelianPembayaran::find($payment->id_pembelianpembayaran)->load('pembelianAlokasiPembayaran');
            $detailTransaksi = [
                [
                    'kode_akun' => '1410',
                    'keterangan' => 'Uang muka pembelian #' . $payment->transaksi_no,
                    'nominaldebit' => $payment->nominal,
                    'nominalkredit' => 0,
                    'ref_id' => null
                ],
                [
                    'kode_akun' => '1001',
                    'keterangan' => 'Uang muka pembelian #' . $payment->transaksi_no,
                    'nominaldebit' => 0,
                    'nominalkredit' => $payment->nominal,
                    'ref_id' => null
                ]
            ];
            $this->entryJurnal($payment->id_transaksi, $detailTransaksi);
            foreach ($payment->pembelianAlokasiPembayaran as $alokasi) {
                $detailTransaksi = [
                    [
                        'kode_akun' => '2001',
                        'keterangan' => 'Pembayaran #' . $alokasi->pembelian->transaksi_no,
                        'nominaldebit' => $alokasi->nominal,
                        'nominalkredit' => 0,
                        'ref_id' => $alokasi->id_pembelianalokasipembayaran
                    ],
                    [
                        'kode_akun' => '1410',
                        'keterangan' => 'Pembayaran #' . $alokasi->pembelian->transaksi_no,
                        'nominaldebit' => 0,
                        'nominalkredit' => $alokasi->nominal,
                        'ref_id' => $alokasi->id_pembelianalokasipembayaran
                    ],
                ];
                $this->entryJurnal($alokasi->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updatePayment($idPembayaran, $request)
    {
        $rules = [
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'catatan' => 'nullable|string',
            'totaldp' => 'required|numeric',
            'pembelianAlokasiPembayaran' => 'array',
            'pembelianAlokasiPembayaran.*.nominalbayar' => 'required|numeric',
            'pembelianAlokasiPembayaran.*.id_pembelian' => 'nullable|numeric',
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $pembayaranOld = (new PembelianPembayaran())->with('pembelianAlokasiPembayaran.pembelian')->where('id_pembelianpembayaran', $idPembayaran)->first();
            $pembayaran = (new PembelianPembayaran())->with('pembelianAlokasiPembayaran.pembelian')->where('id_pembelianpembayaran', $idPembayaran)->first();
            $pembayaran->update([
                'tanggal' => $request['tanggal'],
                'catatan' => $request['catatan'],
                'nominal' => (int)$request['totaldp']
            ]);
            $pembayaran->refresh();
            $oldItem = $pembayaran->pembelianAlokasiPembayaran->keyBy('id_pembelianalokasipembayaran');
            foreach ($request['pembelianAlokasiPembayaran'] as $key => $item) {
                if ($item['_remove_'] == 0) {
                    /* Jika tidak dihapus */
                    if (isset($oldItem[$item['id_pembelianalokasipembayaran']])) {
                        /* Jika di-update */
                        $newData = [];
                        if ($item['nominalbayar'] != $oldItem[$item['id_pembelianalokasipembayaran']]['nominal']) {
                            $newData['nominal'] = (int)$item['nominalbayar'];
                        }
                        if (!empty($newData)) {
                            $this->updateAllocatePaymentToInvoice($item['id_pembelianalokasipembayaran'], $pembayaran, $pembayaranOld, $newData, $oldItem);
                        }
                    } else {
                        /* Jika ditambah baru */
                        $newData = [
                            'id_pembelian' => $item['id_pembelian'],
                            'nominalbayar' => (int)$item['nominalbayar']
                        ];
                        $this->storeAllocatePaymentToInvoice($pembayaran, $newData);
                    }
                } else {
                    /* Jika dihapus */
                    if (isset($oldItem[$item['id_pembelianalokasipembayaran']])) {
                        $this->deleteAllocatePaymentToInvoice($item['id_pembelianalokasipembayaran'], $pembayaran, $oldItem);
                    }
                }
            }
            $pembayaran->refresh();
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaran(?) as sisapembayaran', [$pembayaran->transaksi_no])[0]->sisapembayaran;
            if (0 > $sisaPembayaran) {
                throw new PurchasePaymentException('Total pembayaran melebihi sisa pembayaran');
            }
            $this->deleteJurnal($pembayaran->id_transaksi);
            $detailTransaksi = [
                [
                    'kode_akun' => '1410',
                    'keterangan' => 'Uang muka pembelian #' . $pembayaran->transaksi_no,
                    'nominaldebit' => $pembayaran->nominal,
                    'nominalkredit' => 0,
                    'ref_id' => null
                ],
                [
                    'kode_akun' => '1001',
                    'keterangan' => 'Uang muka pembelian #' . $pembayaran->transaksi_no,
                    'nominaldebit' => 0,
                    'nominalkredit' => $pembayaran->nominal,
                    'ref_id' => null
                ]
            ];
            $this->entryJurnal($pembayaran->id_transaksi, $detailTransaksi);
            foreach ($pembayaran->pembelianAlokasiPembayaran as $alokasi) {
                $this->deleteJurnal($alokasi->id_transaksi);
                $detailTransaksi = [
                    [
                        'kode_akun' => '2001',
                        'keterangan' => 'Pembayaran #' . $alokasi->pembelian->transaksi_no,
                        'nominaldebit' => $alokasi->nominal,
                        'nominalkredit' => 0,
                        'ref_id' => $alokasi->id_pembelianalokasipembayaran
                    ],
                    [
                        'kode_akun' => '1410',
                        'keterangan' => 'Pembayaran #' . $alokasi->pembelian->transaksi_no,
                        'nominaldebit' => 0,
                        'nominalkredit' => $alokasi->nominal,
                        'ref_id' => $alokasi->id_pembelianalokasipembayaran
                    ],
                ];
                $this->entryJurnal($alokasi->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $pembayaran;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function deletePayment($idPembayaran)
    {
        DB::beginTransaction();
        try {
            $pembayaran = (new PembelianPembayaran())->with('pembelianAlokasiPembayaran.pembelian')->where('id_pembelianpembayaran', $idPembayaran)->first();
            $oldItem = $pembayaran->pembelianAlokasiPembayaran->keyBy('id_pembelianalokasipembayaran');
            $this->deleteJurnal($pembayaran->id_transaksi);
            foreach ($pembayaran->pembelianAlokasiPembayaran as $item) {
                $this->deleteJurnal($item->id_transaksi);
                $this->deleteAllocatePaymentToInvoice($item->id_pembelianalokasipembayaran, $pembayaran, $oldItem);
                Transaksi::where('id_transaksi', $item->id_transaksi)->delete();
            }
            $pembayaran->delete();
            Transaksi::where('id_transaksi', $pembayaran->id_transaksi)->delete();
            DB::commit();   
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function storeAllocatePaymentToInvoice($payment, $newData)
    {
        DB::beginTransaction();
        try {
            if(!isset($newData['tanggal'])) {
                $newData['tanggal'] = date('Y-m-d H:i:s');
            }
            $pembelianInvoice = Pembelian::where([['id_pembelian', '=', $newData['id_pembelian']], ['jenis', '=', 'invoice'], ['id_kontak', '=', $payment->id_kontak]])->first();
            if (!$pembelianInvoice) {
                throw new PurchasePaymentException('Supplier invoice tidak valid.');
            }
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaran(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran;
            $sisaTagihan = DB::select('select toko_griyanaura.f_getsisatagihan(?) as sisatagihan', [$pembelianInvoice->transaksi_no])[0]->sisatagihan;
            if ($sisaPembayaran < $newData['nominalbayar']) {
                throw new PurchasePaymentException('Sisa pembayaran tidak mencukupi, sisa pembayaran Anda: ' . $sisaPembayaran);
            }
            if ($sisaTagihan < $newData['nominalbayar']) {
                throw new PurchasePaymentException('Pembayaran melebihi tagihan, sisa tagihan Anda: ' . $sisaTagihan);
            }
            $transaksi = Transaksi::create([
                'id_transaksijenis' => 'pembelianpembayaran_dp',
                'tanggal' => $newData['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $paymentAllocation = PembelianAlokasiPembayaran::create([
                'id_pembelianpembayaran' => $payment->id_pembelianpembayaran,
                'id_pembelianinvoice' => $newData['id_pembelian'],
                'tanggal' => $newData['tanggal'],
                'nominal' => (int)$newData['nominalbayar'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username,
                'id_transaksi' => $transaksi->id_transaksi
            ]);
            $transaksi->update([
                'transaksi_no' => $paymentAllocation->id_pembelianalokasipembayaran
            ]);
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateAllocatePaymentToInvoice($idItem, $payment, $oldPayment, $newData, $oldData)
    {
        /**
         * rencana:
         * cek apakah pembayaran pernah direfund, jika iya maka gagal
         * cek apakah setelah tanggal alokasi pembayaran terdapat retur pembelian, jika iya maka gagal
         */
        DB::beginTransaction();
        try {
            // if (DB::table('toko_griyanaura.tr_pembelianrefunddetail')->where('id_pembelianpembayaran', $payment->id_pembelianpembayaran)->exists()) {
            //     throw new PurchasePaymentException('Pembayaran tidak dapat diubah, karena sudah direfund.');
            // }
            if (DB::table('toko_griyanaura.tr_pembelianretur')->where('id_pembelian', $oldData[$idItem]->id_pembelianinvoice)->where('tanggal', '>', $oldData[$idItem]->tanggal)->exists())
            {
                throw new PurchasePaymentException('Alokasi pembayaran tidak dapat diubah, karena terdapat transaksi retur.');
            }
            $pembelianInvoice = Pembelian::where([['id_pembelian', '=', $oldData[$idItem]->id_pembelianinvoice], ['jenis', '=', 'invoice'], ['id_kontak', '=', $payment->id_kontak]])->first();
            if (!$pembelianInvoice) {
                throw new PurchasePaymentException('Supplier invoice tidak valid.');
            }
            $sisaTagihan = DB::select('select toko_griyanaura.f_getsisatagihan(?) as sisatagihan', [$pembelianInvoice->transaksi_no])[0]->sisatagihan - ($newData['nominal'] - $oldData[$idItem]->nominal);
            if ($sisaTagihan < 0) {
                throw new PurchasePaymentException('Pembayaran melebihi tagihan, sisa tagihan Anda: ' . $sisaTagihan);
            }
            PembelianAlokasiPembayaran::where('id_pembelianalokasipembayaran', $idItem)->update($newData);
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function deleteAllocatePaymentToInvoice($idItem, $payment, $oldData)
    {
        /**
         * rencana:
         * cek apakah pembayaran pernah direfund, jika iya maka gagal
         * cek apakah setelah tanggal alokasi pembayaran terdapat retur pembelian, jika iya maka gagal
         */
        DB::beginTransaction();
        try {
            // if (DB::table('toko_griyanaura.tr_pembelianrefunddetail')->where('id_pembelianpembayaran', $payment->id_pembelianpembayaran)->exists()) {
            //     throw new PurchasePaymentException('Pembayaran tidak dapat dihapus, karena sudah direfund.');
            // }
            if (DB::table('toko_griyanaura.tr_pembelianretur')->where('id_pembelian', $oldData[$idItem]->id_pembelianinvoice)->where('tanggal', '>', $oldData[$idItem]->tanggal)->exists())
            {
                throw new PurchasePaymentException('Alokasi pembayaran tidak dapat dihapus, karena terdapat transaksi retur.');
            }
            PembelianAlokasiPembayaran::where('id_pembelianalokasipembayaran', $idItem)->delete();
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
