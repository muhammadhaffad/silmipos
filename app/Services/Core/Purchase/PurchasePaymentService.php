<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchasePaymentException;
use App\Models\Pembelian;
use App\Models\PembelianAlokasiPembayaran;
use App\Models\PembelianPembayaran;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchasePaymentService 
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
                'catatan' => $request['catatan'],
                'nominal' => (int)$request['total'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            foreach ($request['pembelianAlokasiPembayaran'] as $item) {
                $newData = [
                    'nominalbayar' => $item['nominalbayar'],
                    'id_pembelian' => $item['id_pembelian']
                ];
                $this->storeAllocatePaymentToInvoice($payment, $newData);
            }
            $payment = PembelianPembayaran::find($payment->id_pembelianpembayaran)->load('pembelianAlokasiPembayaran');
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
                        'kode_akun' => '1001',
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
                'id_transaksijenis' => 'pembelianpembayaran_tunai',
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
    protected function updateAllocatePaymentToInvoice()
    {
        /**
         * rencana:
         * cek apakah pembayaran pernah direfund, jika iya maka gagal
         * cek apakah setelah tanggal alokasi pembayaran terdapat retur pembelian, jika iya maka gagal
         */
    }
    protected function deleteAllocatePaymentToInvoice()
    {
        /**
         * rencana:
         * cek apakah pembayaran pernah direfund, jika iya maka gagal
         * cek apakah setelah tanggal alokasi pembayaran terdapat retur pembelian, jika iya maka gagal
         */
    }
}
