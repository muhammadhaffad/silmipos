<?php

namespace App\Services\Core\Sales;

use App\Exceptions\PurchaseOrderException;
use App\Exceptions\SalesOrderException;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesOrderService
{
    use JurnalService;
    public function storeSalesOrder($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|string',
            'penjualanDetail' => 'required|array',
            'penjualanDetail.*.kode_produkvarian' => 'required|string',
            'penjualanDetail.*.qty' => 'required|numeric',
            'penjualanDetail.*.harga' => 'required|numeric',
            'penjualanDetail.*.diskon' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $noTransaksi = DB::select("select ('SO' || lpad(nextval('toko_griyanaura.tr_penjualan_order_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'penjualan_order',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $penjualan = Penjualan::create([
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
            foreach ($request['penjualanDetail'] as $key => $item) {
                PenjualanDetail::create([
                    'id_penjualan' => $penjualan->id_penjualan,
                    'kode_produkvarian' => $item['kode_produkvarian'],
                    'harga' => (int)$item['harga'],
                    'qty' => $item['qty'],
                    'diskonjenis' => 'persen',
                    'diskon' => $item['diskon'] ?: 0,
                    'total' => (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100)),
                    'totalraw' => (int)$item['harga'] * $item['qty'],
                    'id_gudang' => $request['id_gudang']
                ]);
                $rawTotal += (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100));
            }
            $grandTotal = (int)($rawTotal * (1 - $request['diskon'] / 100));
            $penjualan->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal,
                'id_gudang' => $request['id_gudang']
            ]);
            $penjualan->refresh();
            DB::commit();
            return $penjualan;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updateSalesOrder($idpenjualan, $request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|string',
            'penjualanDetail' => 'required|array',
            'penjualanDetail.*.kode_produkvarian' => 'required|string',
            'penjualanDetail.*.qty' => 'required|numeric',
            'penjualanDetail.*.harga' => 'required|numeric',
            'penjualanDetail.*.diskon' => 'nullable|numeric',
            'penjualanDetail.*.id_penjualandetail' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        if ((new Penjualan())->where('id_penjualan', $idpenjualan)->has('penjualanInvoice')->first()) {
            abort(403);
        }
        DB::beginTransaction();
        try {
            $penjualan = (new Penjualan())->with('penjualanDetail')->where('id_penjualan', $idpenjualan)->first();
            $penjualan->update([
                'id_kontak' => $request['id_kontak'],
                'tanggal' => $request['tanggal'],
                'id_gudang' => $request['id_gudang'],
                'diskon' => $request['diskon'],
                'catatan' => $request['catatan']
            ]);
            $penjualan->refresh();
            $penjualanDetailIds = [];
            foreach ($request['penjualanDetail'] as $item) {
                if ($item['_remove_'] == 1) {
                    $penjualanDetailIds[] = $item['id_penjualandetail'];
                }
            }
            PenjualanDetail::whereIn('id_penjualandetail', $penjualanDetailIds)->delete();
            $penjualan->refresh();
            $oldItem = $penjualan->penjualanDetail->keyBy('id_penjualandetail');
            foreach ($request['penjualanDetail'] as $key => $item) {
                if ($item['_remove_'] == 0) {
                    if ($item['id_penjualandetail']) {
                        $newData = [];
                        $kodeProdukVarian = explode(' - ', $item['kode_produkvarian'])[0];
                        if ($kodeProdukVarian != $oldItem[$key]['kode_produkvarian']) {
                            $newData['kode_produkvarian'] = $kodeProdukVarian;
                        }
                        if ($item['qty'] != $oldItem[$key]['qty'] or $item['harga'] != $oldItem[$key]['harga'] or $item['diskon'] != $oldItem[$key]['diskon'] or $penjualan['id_gudang'] != $request['id_gudang']) {
                            $newData['qty'] = $item['qty'];
                            $newData['harga'] = (int)$item['harga'];
                            $newData['diskon'] = $item['diskon'];
                            $newData['id_gudang'] = $request['id_gudang'];
                        }
                        if (!empty($newData)) {
                            $newData['totalraw'] = $newData['qty'] * $newData['harga'];
                            $newData['total'] = $newData['qty'] * $newData['harga'] * (1 - $newData['diskon'] / 100);
                            PenjualanDetail::where('id_penjualandetail', $key)->update($newData);
                        }
                    } else {
                        PenjualanDetail::create([
                            'id_penjualan' => $penjualan->id_penjualan,
                            'kode_produkvarian' => $item['kode_produkvarian'],
                            'harga' => (int)$item['harga'],
                            'qty' => $item['qty'],
                            'diskonjenis' => 'persen',
                            'diskon' => $item['diskon'] ?: 0,
                            'total' => (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100)),
                            'totalraw' => (int)$item['harga'] * $item['qty'],
                            'id_gudang' => $request['id_gudang']
                        ]);
                    }
                }
            }
            $penjualan->refresh();
            $rawTotal = 0;
            foreach ($penjualan->penjualanDetail as $item) {
                $rawTotal += $item['total'];
            }
            $penjualan->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $rawTotal * (1 - $penjualan['diskon'] / 100)
            ]);
            $penjualan->refresh();
            DB::commit();
            return $penjualan;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deleteSalesOrder($idpenjualan)
    {
        DB::beginTransaction();
        try {
            if (Penjualan::where('id_penjualan', $idpenjualan)->has('penjualanInvoice')->first()) {
                throw new SalesOrderException('penjualan order sudah di-invoice, penghapusan tidak diizinkan');
            }
            PenjualanDetail::where('id_penjualan', $idpenjualan)->delete();
            $penjualan = Penjualan::where('id_penjualan', $idpenjualan)->first();
            $penjualan->delete();
            Transaksi::where('id_transaksi', $penjualan->id_transaksi)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function storeToInvoiceSalesOrder($idpenjualan, $request)
    {
        DB::beginTransaction();
        try {
            $penjualan = (new Penjualan())->with(['kontak', 'penjualanDetail' => function ($rel) {
                $rel->leftJoin(DB::raw("(select id_penjualandetailparent, sum(qty) as jumlah_diinvoice from toko_griyanaura.tr_penjualandetail where id_penjualandetailparent is not null group by id_penjualandetailparent) as x"), 'x.id_penjualandetailparent', 'toko_griyanaura.tr_penjualandetail.id_penjualandetail');
            }])->where('id_penjualan', $idpenjualan)->first();
            $noTransaksi = DB::select("select ('SI' || lpad(nextval('toko_griyanaura.tr_penjualan_invoice_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'penjualan_invoice',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $penjualanInvoice = Penjualan::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $penjualan->id_kontak,
                'jenis' => 'invoice',
                'transaksi_no' => $noTransaksi,
                'tanggal' => $request['tanggal'],
                'tanggaltempo' => $request['tanggaltempo'],
                'catatan' => $penjualan->catatan,
                'diskonjenis' => 'persen',
                'diskon' => $penjualan->diskon ?: 0,
                'penjualan_parent' => $penjualan->id_penjualan,
                'id_gudang' => $penjualan->id_gudang
            ]);
            $penjualanDetail = $penjualan->penjualanDetail->keyBy('id_penjualandetail');
            $rawTotal = 0;
            foreach ($request['penjualanDetail'] as $key => $item) {
                if (isset($item['check'])) {
                    if (isset($penjualanDetail[$key]) and $penjualanDetail[$key]['qty'] - $penjualanDetail[$key]['jumlah_diinvoice'] >= $item['qty']) {
                        PenjualanDetail::create([
                            'id_penjualan' => $penjualanInvoice->id_penjualan,
                            'kode_produkvarian' => $penjualanDetail[$key]['kode_produkvarian'],
                            'harga' => (int)$penjualanDetail[$key]['harga'],
                            'qty' => $item['qty'],
                            'diskonjenis' => 'persen',
                            'diskon' => $penjualanDetail[$key]['diskon'] ?: 0,
                            'total' => (int)($penjualanDetail[$key]['harga'] * $item['qty'] * (1 - ($penjualanDetail[$key]['diskon'] ?: 0) / 100)),
                            'totalraw' => (int)$penjualanDetail[$key]['harga'] * $item['qty'],
                            'id_gudang' => $penjualan->id_gudang,
                            'id_penjualandetailparent' => $key
                        ]);
                        $rawTotal += (int)($penjualanDetail[$key]['harga'] * $item['qty'] * (1 - ($penjualanDetail[$key]['diskon'] ?: 0) / 100));
                    }
                }
            }
            $grandTotal = (int)($rawTotal * (1 - $penjualan['diskon'] / 100));
            $penjualanInvoice->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal
            ]);
            $penjualanInvoice = Penjualan::with(['penjualanDetail.produkVarian.produk', 'penjualanDetail.produkPersediaan.produkVarianHarga'])->find($penjualanInvoice->id_penjualan);
            $detailTransaksi = [];
            $detailTransaksi[] = [
                'kode_akun' => '1201',
                'keterangan' => null,
                'nominaldebit' => $penjualanInvoice->grandtotal,
                'nominalkredit' => 0
            ];
            foreach ($penjualanInvoice->penjualanDetail as $item) {
                if ($item->produkVarian->produk->in_stok == true) {
                    $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $item['kode_produkvarian'])->where('id_gudang', $item['id_gudang'])->first();
                    if (!$persediaanProduk) {
                        throw new SalesOrderException('Produk belum tersedia');
                    }
                    ProdukPersediaan::where('kode_produkvarian', $item->kode_produkvarian)->where('id_gudang', $item->id_gudang)->update([
                        'stok' => DB::raw('stok -' . (int)$item->qty)
                    ]);
                    $dataPersediaanDetail = ProdukPersediaanDetail::create([
                        'id_persediaan' => $persediaanProduk->id_persediaan,
                        'tanggal' => $penjualanInvoice->tanggal,
                        'keterangan' => "#{$penjualanInvoice->transaksi_no} Invoice penjualan",
                        'stok_out' => $item->qty,
                        'hargabeli' => (int)($item->produkPersediaan->hargabeli_avg),
                        'hargajual' => (int)($item->harga * (1-$item->diskon/100) * (1-$penjualanInvoice->diskon/100)),
                        'ref_id' => $item->id_penjualandetail
                    ]);
                    $item->hargabeli = $item->produkPersediaan->hargabeli_avg;
                    $item->save();
                }
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpemasukan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty * $item->harga * (1-$item->diskon/100) * (1-$penjualanInvoice->diskon/100)),
                    'ref_id' => $item->id_penjualandetail
                ];
            }
            foreach ($penjualanInvoice->penjualanDetail as $item) {
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpemasukan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->produkPersediaan->hargabeli_avg),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_penjualandetail
                ];
                $detailTransaksi[] = [
                    'kode_akun' => '1301',
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty * $item->produkPersediaan->hargabeli_avg),
                    'ref_id' => $item->id_penjualandetail
                ];
            }
            if (isset($detailTransaksi)) {
                $this->entryJurnal($penjualanInvoice->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $penjualanInvoice;
        } catch (\Exception $e) {
            throw $e;
            DB::rollBack();
        }
    }
}
