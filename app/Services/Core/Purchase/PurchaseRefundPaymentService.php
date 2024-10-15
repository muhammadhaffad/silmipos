<?php
namespace App\Services\Core\Purchase;

use App\Exceptions\PurchasePaymentException;
use App\Models\PembelianPembayaran;
use App\Services\Core\Jurnal\JurnalService;
use Illuminate\Support\Facades\DB;

class PurchaseRefundPaymentService
{
    use JurnalService;
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
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateRefundDetailDP($idItem, $refund, $oldRefund, $newData, $oldData)
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
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function deleteRefundDetailDP($idItem, $refund, $oldData)
    {}
}