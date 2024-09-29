<?php
namespace App\Services\Core\Purchase;

use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderService
{
    public function storePurchaseOrder($request) {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|numeric',
            'pembelianDetail' => 'required|array',
            'pembelianDetail.*.kode_produkvarian' => 'required|string',
            'pembelianDetail.*.qty' => 'required|numeric',
            'pembelianDetail.*.harga' => 'required|numeric',
            'pembelianDetail.*.diskon' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $noTransaksi = DB::select("select ('PO' || lpad(nextval('toko_griyanaura.tr_pembelian_order_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'pembelian_order',
            ]);
            $pembelian = Pembelian::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'jenis' => 'order',
                'transaksi_no' => $noTransaksi,
                'tanggal' => $request['tanggal'],
                'catatan' => $request['catatan'],
                'diskonjenis' => 'persen',
                'diskon' => $request['diskon'] ?: 0,
            ]);
            $rawTotal = 0;
            foreach ($request['pembelianDetail'] as $key => $item) {
                PembelianDetail::create([
                    'id_pembelian' => $pembelian->id_pembelian,
                    'kode_produkvarian' => $item['kode_produkvarian'],
                    'harga' => (int)$item['harga'],
                    'qty' => $item['qty'],
                    'diskonjenis' => 'persen',
                    'diskon' => $item['diskon'] ?: 0,
                    'total' => (int)($item['harga']*$item['qty']*(1-($item['diskon'] ?: 0)/100)),
                    'totalraw' => (int)$item['harga'] * $item['qty'],
                    'id_gudang' => $request['id_gudang']
                ]);
                $rawTotal += (int)($item['harga']*$item['qty']*(1-($item['diskon'] ?: 0)/100));
            }
            $grandTotal = (int)($rawTotal * (1-$request['diskon']/100));
            $pembelian->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal,
                'id_gudang' => $request['id_gudang']
            ]);
            $pembelian->refresh();
            DB::commit();
            return $pembelian;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updatePurchaseOrder($idPembelian, $request) {
        $rules = [
            'id_kontak' => 'required|numeric',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|string',
            'pembelianDetail' => 'required|array',
            'pembelianDetail.*.kode_produkvarian' => 'required|string',
            'pembelianDetail.*.qty' => 'required|numeric',
            'pembelianDetail.*.harga' => 'required|numeric',
            'pembelianDetail.*.diskon' => 'nullable|numeric',
            'pembelianDetail.*.id_pembeliandetail' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        if ((new Pembelian())->where('id_pembelian', $idPembelian)->has('pembelianInvoice')->first()) {
            abort(403);
        }
        DB::beginTransaction();
        try {
            $pembelian = (new Pembelian())->with('pembelianDetail')->where('id_pembelian', $idPembelian)->first();
            $pembelian->update([
                'id_kontak' => $request['id_kontak'],
                'tanggal' => $request['tanggal'],
                'id_gudang' => $request['id_gudang'],
                'diskon' => $request['diskon'],
                'catatan' => $request['catatan']
            ]);
            $pembelian->refresh();
            $pembelianDetailIds = [];
            foreach ($request['pembelianDetail'] as $item) {
                if ($item['_remove_'] == 1) {
                    $pembelianDetailIds[] = $item['id_pembeliandetail'];
                }
            }
            PembelianDetail::whereIn('id_pembeliandetail', $pembelianDetailIds)->delete();
            $pembelian->refresh();
            $oldItem = $pembelian->pembelianDetail->keyBy('id_pembeliandetail');
            foreach ($request['pembelianDetail'] as $key => $item) {
                if ($item['_remove_'] == 0) {
                    if ($item['id_pembeliandetail']) {
                        $newData = [];
                        $kodeProdukVarian = explode(' - ', $item['kode_produkvarian'])[0];
                        if ($kodeProdukVarian != $oldItem[$key]['kode_produkvarian']) {
                            $newData['kode_produkvarian'] = $kodeProdukVarian;
                        }
                        if ($item['qty'] != $oldItem[$key]['qty'] or $item['harga'] != $oldItem[$key]['harga'] or $item['diskon'] != $oldItem[$key]['diskon'] or $pembelian['id_gudang'] != $request['id_gudang']) {
                            $newData['qty'] = $item['qty'];
                            $newData['harga'] = (int)$item['harga'];
                            $newData['diskon'] = $item['diskon'];
                            $newData['id_gudang'] = $request['id_gudang'];
                        }
                        if (!empty($newData)) {
                            $newData['totalraw'] = $newData['qty']*$newData['harga'];
                            $newData['total'] = $newData['qty']*$newData['harga']*(1-$newData['diskon']/100);
                            PembelianDetail::where('id_pembeliandetail', $key)->update($newData);
                        }
                    } else {
                        PembelianDetail::create([
                            'id_pembelian' => $pembelian->id_pembelian,
                            'kode_produkvarian' => $item['kode_produkvarian'],
                            'harga' => (int)$item['harga'],
                            'qty' => $item['qty'],
                            'diskonjenis' => 'persen',
                            'diskon' => $item['diskon'] ?: 0,
                            'total' => (int)($item['harga']*$item['qty']*(1-($item['diskon'] ?: 0)/100)),
                            'totalraw' => (int)$item['harga'] * $item['qty'],
                            'id_gudang' => $request['id_gudang']
                        ]);
                    }
                } 
            }
            $pembelian->refresh();
            $rawTotal = 0;
            foreach ($pembelian->pembelianDetail as $item) {
                $rawTotal += $item['total'];
            }
            $pembelian->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $rawTotal*(1-$pembelian['diskon']/100)
            ]);
            $pembelian->refresh();
            DB::commit();
            return $pembelian;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deletePurchaseOrder($idPembelian) {
        if ((new Pembelian())->where('id_pembelian', $idPembelian)->has('pembelianInvoice')->first()) {
            abort(403);
        }
        DB::beginTransaction();
        try {
            PembelianDetail::where('id_pembelian', $idPembelian)->delete();
            $pembelian = Pembelian::where('id_pembelian', $idPembelian)->first();
            $pembelian->delete();
            Transaksi::where('id_transaksi', $pembelian->id_transaksi)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function toInvoicePurchaseOrder($idPembelian) {
        $pembelian = (new Pembelian())->with(['kontak','pembelianDetail' => function ($rel) {
            $rel->leftJoin(DB::raw("(select id_pembeliandetailparent, sum(qty) as jumlah_diinvoice from toko_griyanaura.tr_pembeliandetail where id_pembeliandetailparent is not null group by id_pembeliandetailparent) as x"), 'x.id_pembeliandetailparent', 'toko_griyanaura.tr_pembeliandetail.id_pembeliandetail');
        } ])->where('id_pembelian', $idPembelian)->first();
        
    }
}