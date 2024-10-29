<?php

namespace App\Services\Core\Sales;

use App\Exceptions\SalesPaymentException;
use App\Models\Penjualan;
use App\Models\PenjualanAlokasiPembayaran;
use App\Models\PenjualanPembayaran;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesPaymentService 
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
            'penjualanAlokasiPembayaran' => 'required|array',
            'penjualanAlokasiPembayaran.*.id_penjualan' => 'required|string',
            'penjualanAlokasiPembayaran.*.nominalbayar' => 'required|numeric',
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $noTransaksi = DB::select("select ('SCS' || lpad(nextval('toko_griyanaura.tr_penjualanpembayaran_tunai_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'penjualanpembayaran_tunai',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $payment = PenjualanPembayaran::create([
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
            $total = 0;
            foreach ($request['penjualanAlokasiPembayaran'] as $item) {
                $total += $item['nominalbayar'];
            }
            if ($total != $request['total']) {
                throw new SalesPaymentException('Total pembayaran tidak sama');
            }
            foreach ($request['penjualanAlokasiPembayaran'] as $item) {
                $newData = [
                    'nominalbayar' => $item['nominalbayar'],
                    'id_penjualan' => $item['id_penjualan']
                ];
                $total += $newData['nominalbayar'];
                $this->storeAllocatePaymentToInvoice($payment, $newData);
            }
            $payment = PenjualanPembayaran::find($payment->id_penjualanpembayaran)->load('penjualanAlokasiPembayaran');
            foreach ($payment->penjualanAlokasiPembayaran as $alokasi) {
                $detailTransaksi = [
                    [
                        'kode_akun' => '1001',
                        'keterangan' => 'Pembayaran #' . $alokasi->penjualan->transaksi_no,
                        'nominaldebit' => $alokasi->nominal,
                        'nominalkredit' => 0,
                        'ref_id' => $alokasi->id_penjualanalokasipembayaran
                    ],
                    [
                        'kode_akun' => '1201',
                        'keterangan' => 'Pembayaran #' . $alokasi->penjualan->transaksi_no,
                        'nominaldebit' => 0,
                        'nominalkredit' => $alokasi->nominal,
                        'ref_id' => $alokasi->id_penjualanalokasipembayaran
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
            'total' => 'required|numeric',
            'penjualanAlokasiPembayaran' => 'array',
            'penjualanAlokasiPembayaran.*.nominalbayar' => 'required|numeric',
            'penjualanAlokasiPembayaran.*.id_penjualan' => 'nullable|numeric',
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $pembayaranOld = (new PenjualanPembayaran())->with('penjualanAlokasiPembayaran.penjualan')->where('id_penjualanpembayaran', $idPembayaran)->first();
            $pembayaran = (new PenjualanPembayaran())->with('penjualanAlokasiPembayaran.penjualan')->where('id_penjualanpembayaran', $idPembayaran)->first();
            $pembayaran->update([
                'tanggal' => $request['tanggal'],
                'catatan' => $request['catatan'],
                'nominal' => (int)$request['total']
            ]);
            $pembayaran->refresh();
            $oldItem = $pembayaran->penjualanAlokasiPembayaran->keyBy('id_penjualanalokasipembayaran');
            $total = 0;
            foreach ($request['penjualanAlokasiPembayaran'] as $item) {
                if ($item['_remove_'] == 0)
                    $total += $item['nominalbayar'];
            }
            if ($total != $request['total']) {
                throw new SalesPaymentException('Total pembayaran tidak sama');
            }
            foreach ($request['penjualanAlokasiPembayaran'] as $key => $item) {
                if ($item['_remove_'] == 0) {
                    /* Jika tidak dihapus */
                    if (isset($oldItem[$item['id_penjualanalokasipembayaran']])) {
                        /* Jika di-update */
                        $newData = [];
                        if ($item['nominalbayar'] != $oldItem[$item['id_penjualanalokasipembayaran']]['nominal']) {
                            $newData['nominal'] = (int)$item['nominalbayar'];
                        }
                        if (!empty($newData)) $this->updateAllocatePaymentToInvoice($item['id_penjualanalokasipembayaran'], $pembayaran, $pembayaranOld, $newData, $oldItem);
                    } else {
                        /* Jika ditambah baru */
                        $newData = [
                            'id_penjualan' => $item['id_penjualan'],
                            'nominalbayar' => (int)$item['nominalbayar']
                        ];
                        $this->storeAllocatePaymentToInvoice($pembayaran, $newData);
                    }
                } else {
                    /* Jika dihapus */
                    if (isset($oldItem[$item['id_penjualanalokasipembayaran']])) {
                        $this->deleteAllocatePaymentToInvoice($item['id_penjualanalokasipembayaran'], $pembayaran, $oldItem);
                    }
                }
            }
            $pembayaran->refresh();
            foreach ($pembayaran->penjualanAlokasiPembayaran as $alokasi) {
                $this->deleteJurnal($alokasi->id_transaksi);
                $detailTransaksi = [
                    [
                        'kode_akun' => '1001',
                        'keterangan' => 'Pembayaran #' . $alokasi->penjualan->transaksi_no,
                        'nominaldebit' => $alokasi->nominal,
                        'nominalkredit' => 0,
                        'ref_id' => $alokasi->id_penjualanalokasipembayaran
                    ],
                    [
                        'kode_akun' => '1201',
                        'keterangan' => 'Pembayaran #' . $alokasi->penjualan->transaksi_no,
                        'nominaldebit' => 0,
                        'nominalkredit' => $alokasi->nominal,
                        'ref_id' => $alokasi->id_penjualanalokasipembayaran
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
            $pembayaran = (new PenjualanPembayaran())->with('penjualanAlokasiPembayaran.penjualan')->where('id_penjualanpembayaran', $idPembayaran)->first();
            $oldItem = $pembayaran->penjualanAlokasiPembayaran->keyBy('id_penjualanalokasipembayaran');
            $this->deleteJurnal($pembayaran->id_transaksi);
            foreach ($pembayaran->penjualanAlokasiPembayaran as $item) {
                $this->deleteJurnal($item->id_transaksi);
                $this->deleteAllocatePaymentToInvoice($item->id_penjualanalokasipembayaran, $pembayaran, $oldItem);
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
            $penjualanInvoice = Penjualan::where([['id_penjualan', '=', $newData['id_penjualan']], ['jenis', '=', 'invoice'], ['id_kontak', '=', $payment->id_kontak]])->first();
            if (!$penjualanInvoice) {
                throw new SalesPaymentException('Supplier invoice tidak valid.');
            }
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaranpenjualan(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran;
            $sisaTagihan = DB::select('select toko_griyanaura.f_getsisatagihanpenjualan(?) as sisatagihan', [$penjualanInvoice->transaksi_no])[0]->sisatagihan;
            if ($sisaPembayaran < $newData['nominalbayar']) {
                throw new SalesPaymentException('Sisa pembayaran tidak mencukupi, sisa pembayaran Anda: ' . $sisaPembayaran);
            }
            if ($sisaTagihan < $newData['nominalbayar']) {
                throw new SalesPaymentException('Pembayaran melebihi tagihan, sisa tagihan Anda: ' . $sisaTagihan);
            }
            $transaksi = Transaksi::create([
                'id_transaksijenis' => 'penjualanpembayaran_tunai',
                'tanggal' => $newData['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $paymentAllocation = PenjualanAlokasiPembayaran::create([
                'id_penjualanpembayaran' => $payment->id_penjualanpembayaran,
                'id_penjualaninvoice' => $newData['id_penjualan'],
                'tanggal' => $newData['tanggal'],
                'nominal' => (int)$newData['nominalbayar'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username,
                'id_transaksi' => $transaksi->id_transaksi
            ]);
            $transaksi->update([
                'transaksi_no' => $paymentAllocation->id_penjualanalokasipembayaran
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
         * cek apakah setelah tanggal alokasi pembayaran terdapat retur penjualan, jika iya maka gagal
         */
        DB::beginTransaction();
        try {
            if (DB::table('toko_griyanaura.tr_penjualanrefunddetail')->where('id_penjualanpembayaran', $payment->id_penjualanpembayaran)->exists()) {
                throw new SalesPaymentException('Pembayaran tidak dapat diubah, karena sudah direfund.');
            }
            if (DB::table('toko_griyanaura.tr_penjualanretur')->where('id_penjualan', $oldData[$idItem]->id_penjualaninvoice)->where('tanggal', '>', $oldData[$idItem]->tanggal)->exists())
            {
                throw new SalesPaymentException('Alokasi pembayaran tidak dapat diubah, karena terdapat transaksi retur.');
            }
            $penjualanInvoice = Penjualan::where([['id_penjualan', '=', $oldData[$idItem]->id_penjualaninvoice], ['jenis', '=', 'invoice'], ['id_kontak', '=', $payment->id_kontak]])->first();
            if (!$penjualanInvoice) {
                throw new SalesPaymentException('Supplier invoice tidak valid.');
            }
            $sisaTagihan = DB::select('select toko_griyanaura.f_getsisatagihanpenjualan(?) as sisatagihan', [$penjualanInvoice->transaksi_no])[0]->sisatagihan - ($newData['nominal'] - $oldData[$idItem]->nominal);
            if ($sisaTagihan < 0) {
                throw new SalesPaymentException('Pembayaran melebihi tagihan, sisa tagihan Anda: ' . $sisaTagihan);
            }
            PenjualanAlokasiPembayaran::where('id_penjualanalokasipembayaran', $idItem)->update($newData);
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
         * cek apakah setelah tanggal alokasi pembayaran terdapat retur penjualan, jika iya maka gagal
         */
        DB::beginTransaction();
        try {
            if (DB::table('toko_griyanaura.tr_penjualanrefunddetail')->where('id_penjualanpembayaran', $payment->id_penjualanpembayaran)->exists()) {
                throw new SalesPaymentException('Pembayaran tidak dapat dihapus, karena sudah direfund.');
            }
            if (DB::table('toko_griyanaura.tr_penjualanretur')->where('id_penjualan', $oldData[$idItem]->id_penjualaninvoice)->where('tanggal', '>', $oldData[$idItem]->tanggal)->exists())
            {
                throw new SalesPaymentException('Alokasi pembayaran tidak dapat dihapus, karena terdapat transaksi retur.');
            }
            PenjualanAlokasiPembayaran::where('id_penjualanalokasipembayaran', $idItem)->delete();
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
