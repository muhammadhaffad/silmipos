<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchaseOrderException;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderService
{
    use JurnalService;
    public function storePurchaseOrder($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|string',
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
                    'total' => (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100)),
                    'totalraw' => (int)$item['harga'] * $item['qty'],
                    'id_gudang' => $request['id_gudang']
                ]);
                $rawTotal += (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100));
            }
            $grandTotal = (int)($rawTotal * (1 - $request['diskon'] / 100));
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
    public function updatePurchaseOrder($idPembelian, $request)
    {
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
                            $newData['totalraw'] = $newData['qty'] * $newData['harga'];
                            $newData['total'] = $newData['qty'] * $newData['harga'] * (1 - $newData['diskon'] / 100);
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
                            'total' => (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100)),
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
                'grandtotal' => $rawTotal * (1 - $pembelian['diskon'] / 100)
            ]);
            $pembelian->refresh();
            DB::commit();
            return $pembelian;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deletePurchaseOrder($idPembelian)
    {
        DB::beginTransaction();
        try {
            if (Pembelian::where('id_pembelian', $idPembelian)->has('pembelianInvoice')->first()) {
                throw new PurchaseOrderException('Pembelian order sudah di-invoice, penghapusan tidak diizinkan');
            }
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
    public function storeToInvoicePurchaseOrder($idPembelian, $request)
    {
        DB::beginTransaction();
        try {
            $pembelian = (new Pembelian())->with(['kontak', 'pembelianDetail' => function ($rel) {
                $rel->leftJoin(DB::raw("(select id_pembeliandetailparent, sum(qty) as jumlah_diinvoice from toko_griyanaura.tr_pembeliandetail where id_pembeliandetailparent is not null group by id_pembeliandetailparent) as x"), 'x.id_pembeliandetailparent', 'toko_griyanaura.tr_pembeliandetail.id_pembeliandetail');
            }])->where('id_pembelian', $idPembelian)->first();
            $noTransaksi = DB::select("select ('PI' || lpad(nextval('toko_griyanaura.tr_pembelian_invoice_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'pembelian_invoice',
            ]);
            $pembelianInvoice = Pembelian::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $pembelian->id_kontak,
                'jenis' => 'invoice',
                'transaksi_no' => $noTransaksi,
                'tanggal' => $pembelian->tanggal,
                'tanggaltempo' => $request['tanggaltempo'],
                'catatan' => $pembelian->catatan,
                'diskonjenis' => 'persen',
                'diskon' => $pembelian->diskon ?: 0,
                'pembelian_parent' => $pembelian->id_pembelian,
                'id_gudang' => $pembelian->id_gudang
            ]);
            $pembelianDetail = $pembelian->pembelianDetail->keyBy('id_pembeliandetail');
            $rawTotal = 0;
            foreach ($request['pembelianDetail'] as $key => $item) {
                if (isset($item['check'])) {
                    if (isset($pembelianDetail[$key]) and $pembelianDetail[$key]['qty'] - $pembelianDetail[$key]['jumlah_diinvoice'] >= $item['qty']) {
                        PembelianDetail::create([
                            'id_pembelian' => $pembelianInvoice->id_pembelian,
                            'kode_produkvarian' => $pembelianDetail[$key]['kode_produkvarian'],
                            'harga' => (int)$pembelianDetail[$key]['harga'],
                            'qty' => $item['qty'],
                            'diskonjenis' => 'persen',
                            'diskon' => $pembelianDetail[$key]['diskon'] ?: 0,
                            'total' => (int)($pembelianDetail[$key]['harga'] * $item['qty'] * (1 - ($pembelianDetail[$key]['diskon'] ?: 0) / 100)),
                            'totalraw' => (int)$pembelianDetail[$key]['harga'] * $item['qty'],
                            'id_gudang' => $pembelian->id_gudang,
                            'id_pembeliandetailparent' => $key
                        ]);
                        $rawTotal += (int)($pembelianDetail[$key]['harga'] * $item['qty'] * (1 - ($pembelianDetail[$key]['diskon'] ?: 0) / 100));
                    }
                }
            }
            $grandTotal = (int)($rawTotal * (1 - $pembelian['diskon'] / 100));
            $pembelianInvoice->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal
            ]);
            $pembelianInvoice = Pembelian::with('pembelianDetail.produkVarian.produk')->find($pembelianInvoice->id_pembelian);
            $detailTransaksi = [];
            foreach ($pembelianInvoice->pembelianDetail as $item) {
                if ($item->produkVarian->produk->in_stok == true) {
                    $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $item['kode_produkvarian'])->where('id_gudang', $item['id_gudang'])->first();
                    if (!$persediaanProduk) {
                        if (ProdukVarianHarga::where('kode_produkvarian', $item['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $item['id_gudang'])->first()) {
                            $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $item['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $item['id_gudang'])->first()->id_produkvarianharga;
                        } else {
                            $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $item['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                        }
                        $persediaanProduk = ProdukPersediaan::create([
                            'id_gudang' => $item['id_gudang'],
                            'kode_produkvarian' => $item['kode_produkvarian'],
                            'stok' => 0,
                            'default_varianharga' => $defaultVarianHarga
                        ]);
                    }
                    ProdukPersediaan::where('kode_produkvarian', $item->kode_produkvarian)->where('id_gudang', $item->id_gudang)->update([
                        'stok' => DB::raw('stok +' . (int)$item->qty)
                    ]);
                    $dataPersediaanDetail = ProdukPersediaanDetail::create([
                        'id_persediaan' => $persediaanProduk->id_persediaan,
                        'tanggal' => $pembelianInvoice->tanggal,
                        'keterangan' => "#{$pembelianInvoice->transaksi_no} Invoice Pembelian",
                        'stok_in' => $item->qty,
                        'hargabeli' => (int)($item->harga * (1-$item->diskon/100) * (1-$pembelianInvoice->diskon/100))
                    ]);
                }
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->harga * (1-$item->diskon/100) * (1-$pembelianInvoice->diskon/100)),
                    'nominalkredit' => 0
                ];
            }
            $detailTransaksi[] = [
                'kode_akun' => '2001',
                'keterangan' => null,
                'nominaldebit' => 0,
                'nominalkredit' => $pembelianInvoice->grandtotal
            ];
            if (isset($detailTransaksi)) {
                $this->entryJurnal($pembelianInvoice->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $pembelianInvoice;
        } catch (\Exception $e) {
            throw $e;
            DB::rollBack();
        }
    }
}
