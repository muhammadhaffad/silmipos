<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchasePaymentException;
use App\Models\PembelianPembayaran;
use App\Models\PembelianRefund;
use App\Models\PembelianRefundDetail;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseRefundPaymentService
{
    use JurnalService;
    public function storeRefundDP($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'pembelianRefundDetail.*.id_pembelianpembayaran' => 'required|numeric',
            'pembelianRefundDetail.*.nominal' => 'required|numeric',
            'total' => 'required|numeric',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            if (!$request['transaksi_no'])
                $request['transaksi_no'] = DB::select("select ('PRF' || lpad(nextval('toko_griyanaura.tr_pembelianrefund_id_pembelianrefund_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $request['transaksi_no'],
                'id_transaksijenis' => 'pembelianpembayaran_refund',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $refund = PembelianRefund::create([
                'transaksi_no' => $request['transaksi_no'],
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'tanggal' => $request['tanggal'],
                'catatan' => $request['catatan'],
                'total' => (int)$request['total'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $total = 0;
            foreach ($request['pembelianRefundDetail'] as $item) {
                $total += $item['nominal'];
            }
            if ($total != $request['total']) {
                throw new PurchasePaymentException('Total pembayaran tidak sama');
            }
            foreach ($request['pembelianRefundDetail'] as $item) {
                $newData = [
                    'nominal' => $item['nominal'],
                    'id_pembelianpembayaran' => $item['id_pembelianpembayaran']
                ];
                $this->storeRefundDetailDP($refund, $newData);
            }
            $refund->load('pembelianRefundDetail');
            $detailTransaksi = [];
            $detailTransaksi[] = [
                'kode_akun' => '1001',
                'keterangan' => 'Refund pembayaran pembelian #' . $refund->transaksi_no,
                'nominaldebit' => $refund->total,
                'nominalkredit' => 0,
                'ref_id' => null
            ];
            foreach ($refund->pembelianRefundDetail as $pay) {
                $detailTransaksi[] = [
                    'kode_akun' => '1410',
                    'keterangan' => 'Refund pembayaran pembelian #' . $refund->transaksi_no,
                    'nominaldebit' => 0,
                    'nominalkredit' => $pay->nominal,
                    'ref_id' => $pay->id_pembelianrefunddetail
                ];
            }
            $this->entryJurnal($refund->id_transaksi, $detailTransaksi);
            DB::commit();
            return $refund;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function updateRefundDP($idRefund, $request)
    {
        $rules = [
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'pembelianRefundDetail.*.id_pembelianpembayaran' => 'nullable|numeric',
            'pembelianRefundDetail.*.id_pembelianrefunddetail' => 'nullable|numeric',
            'pembelianRefundDetail.*.nominal' => 'required|numeric',
            'total' => 'required|numeric',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $refund = PembelianRefund::with('pembelianRefundDetail.pembelianPembayaranDP')->find($idRefund);
            $total = 0;
            foreach ($request['pembelianRefundDetail'] as $item) {
                if ($item['_remove_'] == 0) {
                    $total += $item['nominal'];
                }
            }
            if ($total != $request['total']) {
                throw new PurchasePaymentException('Total pembayaran tidak sama');
            }
            $refund->total = (int)$request['total'];
            $refund->tanggal = $request['tanggal'];
            $refund->catatan = $request['catatan'];
            $refund->save();
            $oldItem = $refund->pembelianRefundDetail->keyBy('id_pembelianrefunddetail');
            foreach ($request['pembelianRefundDetail'] as $item) {
                if ($item['_remove_'] == 0) {
                    /* Jika tidak dihapus */
                    if (isset($oldItem[$item['id_pembelianrefunddetail']])) {
                        /* Jika di-update */
                        $newData = [];
                        if ($item['nominal'] != $oldItem[$item['id_pembelianrefunddetail']]['nominal']) {
                            $newData['nominal'] = (int)$item['nominal'];
                        }
                        if (!empty($newData)) {
                            // dd($oldItem[$item['id_pembelianrefunddetail']]);
                            $this->updateRefundDetailDP($item['id_pembelianrefunddetail'], $newData, $oldItem);
                        }
                    } else {
                        /* Jika ditambah baru */
                        $newData = [
                            'id_pembelianpembayaran' => $item['id_pembelianpembayaran'],
                            'nominal' => (int)$item['nominal']
                        ];
                        $this->storeRefundDetailDP($refund, $newData);
                    }
                } else {
                    /* Jika dihapus */
                    if (isset($oldItem[$item['id_pembelianrefunddetail']])) {
                        $this->deleteRefundDetailDP($item['id_pembelianrefunddetail'], $oldItem);
                    }
                }
            }
            $refund->refresh();
            $this->deleteJurnal($refund->id_transaksi);
            $detailTransaksi = array();
            $detailTransaksi[] = [
                'kode_akun' => '1001',
                'keterangan' => 'Refund pembayaran pembelian #' . $refund->transaksi_no,
                'nominaldebit' => $refund->total,
                'nominalkredit' => 0,
                'ref_id' => null
            ];
            foreach ($refund->pembelianRefundDetail as $pay) {
                $detailTransaksi[] = [
                    'kode_akun' => '1410',
                    'keterangan' => 'Refund pembayaran pembelian #' . $refund->transaksi_no,
                    'nominaldebit' => 0,
                    'nominalkredit' => $pay->nominal,
                    'ref_id' => $pay->id_pembelianrefunddetail
                ];
            }
            $this->entryJurnal($refund->id_transaksi, $detailTransaksi);
            DB::commit();
            return $refund;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function deleteRefundDP($idRefund)
    {
        DB::beginTransaction();
        try {
            $refund = PembelianRefund::with('pembelianRefundDetail.pembelianPembayaranDP')->find($idRefund);
            $oldItem = $refund->pembelianRefundDetail->keyBy('id_pembelianrefunddetail');
            foreach ($refund->pembelianRefundDetail as $item) {
                /* Jika dihapus */
                $this->deleteRefundDetailDP($item->id_pembelianrefunddetail, $oldItem);
            }
            $refund->delete();
            $this->deleteJurnal($refund->id_transaksi);
            DB::commit();
            return $refund;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function storeRefundDetailDP($refund, $newData)
    {
        DB::beginTransaction();
        try {
            $payment = PembelianPembayaran::find($newData['id_pembelianpembayaran']);
            if ($payment->jenisbayar != 'DP') {
                throw new PurchasePaymentException('Pembayaran bukan DP');
            }
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaran(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran;
            if ($sisaPembayaran < $newData['nominal'])
                throw new PurchasePaymentException('Sisa pembayaran tidak mencukupi');
            $pembelianRefundDetail = PembelianRefundDetail::create([
                'id_pembelianrefund' => $refund->id_pembelianrefund,
                'id_pembelianpembayaran' => $payment->id_pembelianpembayaran,
                'nominal' => (int)$newData['nominal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            DB::commit();
            return $pembelianRefundDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateRefundDetailDP($idItem, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            if ($oldData[$idItem]->pembelianPembayaranDP->jenisbayar != 'DP') {
                throw new PurchasePaymentException('Pembayaran bukan DP');
            }
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaran(?) as sisapembayaran', [$oldData[$idItem]->pembelianPembayaranDP->transaksi_no])[0]->sisapembayaran + $oldData[$idItem]->nominal;
            if ($sisaPembayaran < $newData['nominal'])
                throw new PurchasePaymentException('Sisa pembayaran tidak mencukupi');
            $pembelianRefundDetail = PembelianRefundDetail::find($oldData[$idItem]->id_pembelianrefunddetail);
            $pembelianRefundDetail->nominal = (int)$newData['nominal'];
            $pembelianRefundDetail->save();
            DB::commit();
            return $pembelianRefundDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function deleteRefundDetailDP($idItem, $oldData)
    {
        DB::beginTransaction();
        try {
            $pembelianRefundDetail = PembelianRefundDetail::find($idItem);
            $pembelianRefundDetail->delete();
            DB::commit();
            return $pembelianRefundDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
