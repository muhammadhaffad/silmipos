<?php

namespace App\Services\Core\Sales;

use App\Exceptions\SalesPaymentException;
use App\Models\PenjualanPembayaran;
use App\Models\PenjualanRefund;
use App\Models\PenjualanRefundDetail;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesRefundPaymentService
{
    use JurnalService;
    public function storeRefundDP($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'penjualanRefundDetail.*.id_penjualanpembayaran' => 'required|numeric',
            'penjualanRefundDetail.*.nominal' => 'required|numeric',
            'total' => 'required|numeric',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            if (!$request['transaksi_no'])
                $request['transaksi_no'] = DB::select("select ('PRF' || lpad(nextval('toko_griyanaura.tr_penjualanrefund_id_penjualanrefund_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $request['transaksi_no'],
                'id_transaksijenis' => 'penjualanpembayaran_refund',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $refund = PenjualanRefund::create([
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
            foreach ($request['penjualanRefundDetail'] as $item) {
                $total += $item['nominal'];
            }
            if ($total != $request['total']) {
                throw new SalesPaymentException('Total pembayaran tidak sama');
            }
            foreach ($request['penjualanRefundDetail'] as $item) {
                $newData = [
                    'nominal' => $item['nominal'],
                    'id_penjualanpembayaran' => $item['id_penjualanpembayaran']
                ];
                $this->storeRefundDetailDP($refund, $newData);
            }
            $refund->load('penjualanRefundDetail');
            $detailTransaksi = [];
            $detailTransaksi[] = [
                'kode_akun' => '2201',
                'keterangan' => 'Refund pembayaran penjualan #' . $refund->transaksi_no,
                'nominaldebit' => $refund->total,
                'nominalkredit' => 0,
                'ref_id' => null
            ];
            foreach ($refund->penjualanRefundDetail as $pay) {
                $detailTransaksi[] = [
                    'kode_akun' => '1001',
                    'keterangan' => 'Refund pembayaran penjualan #' . $refund->transaksi_no,
                    'nominaldebit' => 0,
                    'nominalkredit' => $pay->nominal,
                    'ref_id' => $pay->id_penjualanrefunddetail
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
            'penjualanRefundDetail.*.id_penjualanpembayaran' => 'nullable|numeric',
            'penjualanRefundDetail.*.id_penjualanrefunddetail' => 'nullable|numeric',
            'penjualanRefundDetail.*.nominal' => 'required|numeric',
            'total' => 'required|numeric',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $refund = PenjualanRefund::with('penjualanRefundDetail.penjualanPembayaranDP')->find($idRefund);
            $total = 0;
            foreach ($request['penjualanRefundDetail'] as $item) {
                if ($item['_remove_'] == 0) {
                    $total += $item['nominal'];
                }
            }
            if ($total != $request['total']) {
                throw new SalesPaymentException('Total pembayaran tidak sama');
            }
            $refund->total = (int)$request['total'];
            $refund->tanggal = $request['tanggal'];
            $refund->catatan = $request['catatan'];
            $refund->save();
            $oldItem = $refund->penjualanRefundDetail->keyBy('id_penjualanrefunddetail');
            foreach ($request['penjualanRefundDetail'] as $item) {
                if ($item['_remove_'] == 0) {
                    /* Jika tidak dihapus */
                    if (isset($oldItem[$item['id_penjualanrefunddetail']])) {
                        /* Jika di-update */
                        $newData = [];
                        if ($item['nominal'] != $oldItem[$item['id_penjualanrefunddetail']]['nominal']) {
                            $newData['nominal'] = (int)$item['nominal'];
                        }
                        if (!empty($newData)) {
                            // dd($oldItem[$item['id_penjualanrefunddetail']]);
                            $this->updateRefundDetailDP($item['id_penjualanrefunddetail'], $newData, $oldItem);
                        }
                    } else {
                        /* Jika ditambah baru */
                        $newData = [
                            'id_penjualanpembayaran' => $item['id_penjualanpembayaran'],
                            'nominal' => (int)$item['nominal']
                        ];
                        $this->storeRefundDetailDP($refund, $newData);
                    }
                } else {
                    /* Jika dihapus */
                    if (isset($oldItem[$item['id_penjualanrefunddetail']])) {
                        $this->deleteRefundDetailDP($item['id_penjualanrefunddetail'], $oldItem);
                    }
                }
            }
            $refund->refresh();
            $this->deleteJurnal($refund->id_transaksi);
            $detailTransaksi = array();
            $detailTransaksi[] = [
                'kode_akun' => '2201',
                'keterangan' => 'Refund pembayaran penjualan #' . $refund->transaksi_no,
                'nominaldebit' => $refund->total,
                'nominalkredit' => 0,
                'ref_id' => null
            ];
            foreach ($refund->penjualanRefundDetail as $pay) {
                $detailTransaksi[] = [
                    'kode_akun' => '1001',
                    'keterangan' => 'Refund pembayaran penjualan #' . $refund->transaksi_no,
                    'nominaldebit' => 0,
                    'nominalkredit' => $pay->nominal,
                    'ref_id' => $pay->id_penjualanrefunddetail
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
            $refund = PenjualanRefund::with('penjualanRefundDetail.penjualanPembayaranDP')->find($idRefund);
            $oldItem = $refund->penjualanRefundDetail->keyBy('id_penjualanrefunddetail');
            foreach ($refund->penjualanRefundDetail as $item) {
                /* Jika dihapus */
                $this->deleteRefundDetailDP($item->id_penjualanrefunddetail, $oldItem);
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
            $payment = PenjualanPembayaran::find($newData['id_penjualanpembayaran']);
            if ($payment->jenisbayar != 'DP') {
                throw new SalesPaymentException('Pembayaran bukan DP');
            }
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaranpenjualan(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran;
            if ($sisaPembayaran < $newData['nominal'])
                throw new SalesPaymentException('Sisa pembayaran tidak mencukupi');
            $penjualanRefundDetail = PenjualanRefundDetail::create([
                'id_penjualanrefund' => $refund->id_penjualanrefund,
                'id_penjualanpembayaran' => $payment->id_penjualanpembayaran,
                'nominal' => (int)$newData['nominal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            DB::commit();
            return $penjualanRefundDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateRefundDetailDP($idItem, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            if ($oldData[$idItem]->penjualanPembayaranDP->jenisbayar != 'DP') {
                throw new SalesPaymentException('Pembayaran bukan DP');
            }
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaranpenjualan(?) as sisapembayaran', [$oldData[$idItem]->penjualanPembayaranDP->transaksi_no])[0]->sisapembayaran + $oldData[$idItem]->nominal;
            if ($sisaPembayaran < $newData['nominal'])
                throw new SalesPaymentException('Sisa pembayaran tidak mencukupi');
            $penjualanRefundDetail = PenjualanRefundDetail::find($oldData[$idItem]->id_penjualanrefunddetail);
            $penjualanRefundDetail->nominal = (int)$newData['nominal'];
            $penjualanRefundDetail->save();
            DB::commit();
            return $penjualanRefundDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function deleteRefundDetailDP($idItem, $oldData)
    {
        DB::beginTransaction();
        try {
            $penjualanRefundDetail = PenjualanRefundDetail::find($idItem);
            $penjualanRefundDetail->delete();
            DB::commit();
            return $penjualanRefundDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
