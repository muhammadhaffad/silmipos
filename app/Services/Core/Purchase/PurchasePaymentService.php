<?php

namespace App\Services\Core\Purchase;

use App\Models\PembelianAlokasiPembayaran;
use App\Models\PembelianPembayaran;
use App\Models\Transaksi;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchasePaymentService 
{
    public function storePayment($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_kontak' => 'required|numeric',
            'catatan' => 'nullable|string',
            'pembelianAlokasiPembayaran' => 'required|array',
            'pembelianAlokasiPembayaran.*.id_pembelian' => 'required|string',
            'pembelianAlokasiPembayaran.*.nominalbayar' => 'required|numeric',
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $noTransaksi = DB::select("select ('PCS' || lpad(nextval('toko_griyanaura.tr_pembelianpembayaran_tunai_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'pembelianpembayaran_tunai',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $payment = PembelianPembayaran::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'dariakun' => '1001',
                'jenisbayar' => 'tunai',
                'tanggal' => $request['tanggal'],
                'catatan' => $request['catatan']
            ]);
            foreach ($request['pembelianAlokasiPembayaran'] as $item) {
                
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function storeAllocatePaymentToInvoice($payment, $newData)
    {
        if(!isset($newData['tanggal'])) {
            $newData['tanggal'] = date('Y-m-d');
        }
        $paymentAllocation = PembelianAlokasiPembayaran::create([
            'tanggal' => $newData['tanggal'],
            ''
        ]);
    }
    protected function updateAllocatePaymentToInvoice()
    {
    }
    protected function deleteAllocatePaymentToInvoice()
    {
    }
}
