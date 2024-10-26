<?php

namespace App\Services\Core\Sales;

use App\Exceptions\SalesInvoiceException;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesInvoiceService
{
    use JurnalService;
    public function storeSalesInvoice($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'tanggaltempo' => 'required|date_format:Y-m-d H:i:s',
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
            $noTransaksi = DB::select("select ('PI' || lpad(nextval('toko_griyanaura.tr_penjualan_invoice_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'penjualan_invoice',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $penjualanInvoice = Penjualan::create([
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'jenis' => 'invoice',
                'transaksi_no' => $noTransaksi,
                'tanggal' => $request['tanggal'],
                'tanggaltempo' => $request['tanggaltempo'],
                'catatan' => $request['catatan'],
                'diskonjenis' => 'persen',
                'diskon' => $request['diskon'] ?: 0,
            ]);
            $rawTotal = 0;
            foreach ($request['penjualanDetail'] as $key => $item) {
                $newData = [
                    'kode_produkvarian' => $item['kode_produkvarian'],
                    'harga' => (int)$item['harga'],
                    'qty' => $item['qty'],
                    'diskon' => $item['diskon'] ?: 0,
                    'id_gudang' => $request['id_gudang']
                ];
                $this->storeSalesInvoiceItem($penjualanInvoice, $newData);
                $rawTotal += (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100));
            }
            $grandTotal = (int)($rawTotal * (1 - $request['diskon'] / 100));
            $penjualanInvoice->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal,
                'id_gudang' => $request['id_gudang']
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
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpemasukan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $penjualanInvoice->diskon / 100)),
                    'ref_id' => $item->id_penjualandetail
                ];
            }
            foreach ($penjualanInvoice->penjualanDetail as $item) {
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunbiaya,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->hargabeli),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_penjualandetail
                ];
                $detailTransaksi[] = [
                    'kode_akun' => '1301',
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty * $item->hargabeli),
                    'ref_id' => $item->id_penjualandetail
                ];
            }
            if (isset($detailTransaksi)) {
                $this->entryJurnal($penjualanInvoice->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $penjualanInvoice;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updateSalesInvoice($idPenjualan, $request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|string',
            'penjualanDetail' => 'required|array',
            'penjualanDetail.*.kode_produkvarian' => 'nullable|string',
            'penjualanDetail.*.qty' => 'required|numeric',
            'penjualanDetail.*.harga' => 'required|numeric',
            'penjualanDetail.*.diskon' => 'nullable|numeric',
            'penjualanDetail.*.id_penjualandetail' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $penjualanOld = (new Penjualan())->with('penjualanDetail.produkPersediaan', 'penjualanDetail.produkVarian.produk')->where('id_penjualan', $idPenjualan)->first();
            $penjualan = (new Penjualan())->with('penjualanDetail.produkPersediaan', 'penjualanDetail.produkVarian.produk')->where('id_penjualan', $idPenjualan)->first();
            $penjualan->update([
                'id_kontak' => $request['id_kontak'],
                'tanggal' => $request['tanggal'],
                'id_gudang' => $request['id_gudang'],
                'diskon' => $request['diskon'],
                'catatan' => $request['catatan']
            ]);
            $penjualan->refresh();
            $oldItem = $penjualan->penjualanDetail->keyBy('id_penjualandetail');

            foreach ($request['penjualanDetail'] as $key => $item) {
                if ($item['_remove_'] == 0) {
                    /* Jika tidak dihapus */
                    if (isset($oldItem[$item['id_penjualandetail']])) {
                        /* Jika di-update */
                        $newData = [];
                        if ($item['qty'] != $oldItem[$item['id_penjualandetail']]['qty'] or $item['harga'] != $oldItem[$item['id_penjualandetail']]['harga'] or $item['diskon'] != $oldItem[$item['id_penjualandetail']]['diskon'] or $penjualan['id_gudang'] != $request['id_gudang']) {
                            $newData['qty'] = $item['qty'];
                            $newData['harga'] = (int)$item['harga'];
                            $newData['diskon'] = $item['diskon'];
                            $newData['id_gudang'] = $request['id_gudang'];
                            $newData['totalraw'] = $newData['qty'] * $newData['harga'];
                            $newData['total'] = $newData['qty'] * $newData['harga'] * (1 - $newData['diskon'] / 100);
                        }
                        $this->updateSalesInvoiceItem($item['id_penjualandetail'], $penjualan, $penjualanOld, $newData, $oldItem);
                    } else {
                        /* Jika ditambah baru */
                        $newData = [
                            'kode_produkvarian' => $item['kode_produkvarian'],
                            'harga' => (int)$item['harga'],
                            'qty' => $item['qty'],
                            'diskon' => $item['diskon'] ?: 0,
                            'id_gudang' => $request['id_gudang']
                        ];
                        $this->storeSalesInvoiceItem($penjualan, $newData);
                    }
                } else {
                    /* Jika dihapus */
                    if (isset($oldItem[$item['id_penjualandetail']])) {
                        $this->deleteSalesInvoiceItem($item['id_penjualandetail'], $penjualan, $oldItem);
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
            /* Delete pencatatan jurnal */
            $this->deleteJurnal($penjualan->id_transaksi);
            /* Pencatatan ulang jurnal */
            $penjualan = Penjualan::with(['penjualanDetail.produkVarian.produk', 'penjualanDetail.produkPersediaan.produkVarianHarga'])->find($penjualan->id_penjualan);
            $detailTransaksi = [];
            $detailTransaksi[] = [
                'kode_akun' => '1201',
                'keterangan' => null,
                'nominaldebit' => 0,
                'nominalkredit' => 0
            ];
            $total = 0;
            foreach ($penjualan->penjualanDetail as $item) {
                $total += (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $penjualan->diskon / 100));
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $penjualan->diskon / 100)),
                    'ref_id' => $item->id_penjualandetail
                ];
            }
            if ($penjualan->grandtotal - $total > 0) {
                $detailTransaksi[] = [
                    'kode_akun' => '8001',
                    'keterangan' => null,
                    'nominaldebit' => $penjualan->grandtotal - $total,
                    'nominalkredit' => 0
                ];
            }
            foreach ($penjualan->penjualanDetail as $item) {
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunbiaya,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->hargabeli),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_penjualandetail
                ];
                $detailTransaksi[] = [
                    'kode_akun' => '1301',
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty * $item->hargabeli),
                    'ref_id' => $item->id_penjualandetail
                ];
            }
            if (isset($detailTransaksi)) {
                $detailTransaksi[0]['nominaldebit'] = $total;
                $this->entryJurnal($penjualan->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $penjualan;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deleteSalesInvoice($idPenjualan)
    {
        DB::beginTransaction();
        try {
            $penjualan = (new Penjualan())->with('penjualanDetail.produkPersediaan', 'penjualanDetail.produkVarian.produk')->where('id_penjualan', $idPenjualan)->first();
            $oldItem = $penjualan->penjualanDetail->keyBy('id_penjualandetail');
            foreach ($penjualan->penjualanDetail as $item) {
                $this->deleteSalesInvoiceItem($item->id_penjualandetail, $penjualan, $oldItem);
            }
            $this->deleteJurnal($penjualan->id_transaksi);
            $penjualan->delete();
            Transaksi::where('id_transaksi', $penjualan->id_transaksi)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function storeSalesInvoiceItem($invoice, $newData)
    {
        DB::beginTransaction();
        try {
            $penjualanDetail = PenjualanDetail::create([
                'id_penjualan' => $invoice->id_penjualan,
                'kode_produkvarian' => $newData['kode_produkvarian'],
                'harga' => (int)$newData['harga'],
                'qty' => $newData['qty'],
                'diskonjenis' => 'persen',
                'diskon' => $newData['diskon'] ?: 0,
                'total' => (int)($newData['harga'] * $newData['qty'] * (1 - ($newData['diskon'] ?: 0) / 100)),
                'totalraw' => (int)$newData['harga'] * $newData['qty'],
                'id_gudang' => $newData['id_gudang']
            ]);
            $penjualanDetail->load(['produkVarian.produk', 'produkPersediaan']);
            if ($penjualanDetail->produkVarian->produk->in_stok == true) {
                if (!$penjualanDetail->produkPersediaan) {
                    throw new SalesInvoiceException('Produk belum tersedia');
                }
                $penjualanDetail->produkPersediaan->update([
                    'stok' => DB::raw('stok -' . number($newData['qty']))
                ]);
                $penjualanDetail->hargabeli = $penjualanDetail->produkPersediaan->hargabeli_avg;
                $penjualanDetail->save();
                $dataPersediaanDetail = ProdukPersediaanDetail::create([
                    'id_persediaan' => $penjualanDetail->produkPersediaan->id_persediaan,
                    'tanggal' => $invoice->tanggal,
                    'keterangan' => "#{$invoice->transaksi_no} Store item penjualan invoice",
                    'stok_out' => $penjualanDetail->qty,
                    'hargabeli' => (int)($penjualanDetail->produkPersediaan->hargabeli_avg),
                    'hargajual' => (int)($penjualanDetail->harga * (1-$penjualanDetail->diskon/100) * (1-$invoice->diskon/100)),
                    'ref_id' => $penjualanDetail->id_penjualandetail
                ]);
            }
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateSalesInvoiceItem($idItem, $invoice, $oldInvoice, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            if (!empty($newData)) {
                $qtyTelahDiretur = DB::select('(select sum(qty) as jumlah from toko_griyanaura.tr_penjualanreturdetail where id_penjualandetail = ?)', [$idItem])[0]->jumlah;
                if (DB::select('select exists(select 1 from toko_griyanaura.tr_penjualanreturdetail where id_penjualandetail = ?)', [$idItem])[0]->exists) {
                    if ($newData['harga'] != $oldData[$idItem]->harga or $newData['diskon'] != $oldData[$idItem]->diskon) {
                        throw new SalesInvoiceException('Produk yang sudah diretur hanya boleh merubah jumlah saja');
                    }
                    if ($qtyTelahDiretur > $newData['qty']) {
                        throw new SalesInvoiceException('Jumlah produk kurang dari jumlah yang diretur');
                    }
                }
                /* $diBayar = DB::select('select coalesce(sum(nominal),0) as jumlah from toko_griyanaura.tr_penjualanalokasipembayaran where id_penjualaninvoice = ?', [$invoice->id_penjualan])[0]->jumlah;
                $diRetur = DB::select('select coalesce(sum(grandtotal),0) as jumlah from toko_griyanaura.tr_penjualanretur where id_penjualan = ?', [$invoice->id_penjualan])[0]->jumlah; */
                if (DB::select('select toko_griyanaura.f_getsisatagihanpenjualan(?) as sisatagihan', [$invoice->transaksi_no])[0]->sisatagihan < ($oldData[$idItem]->total - $newData['total']) * (1 - $invoice->diskon / 100)) {
                    throw new SalesInvoiceException('Sisa tagihan tidak boleh minus');
                }
                PenjualanDetail::where('id_penjualandetail', $idItem)->update($newData);
            }
            if ($oldData[$idItem]->produkVarian->produk->in_stok == true) {
                /* Kurangi / Tambah Persediaan */
                if (!empty($newData)) {
                    $selisih = $newData['qty'] - $oldData[$idItem]['qty'];
                    if ($selisih > 0) {
                        $oldData[$idItem]->produkPersediaan->decrement('stok', $selisih);
                    } else if ($selisih < 0) {
                        $oldData[$idItem]->produkPersediaan->increment('stok', $selisih);
                    }
                }
                /* Tambah persediaan detail (untuk riwayat persediaan) */
                if ($oldInvoice->diskon != $invoice->diskon or !empty($newData)) {
                    ProdukPersediaanDetail::create([
                        'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                        'tanggal' => $invoice->tanggal,
                        'keterangan' => "#{$invoice->transaksi_no} Update item penjualan invoice",
                        'stok_in' => $oldData[$idItem]->qty,
                        'hargabeli' => (int)($oldData[$idItem]->hargabeli),
                        'hargajual' => (int)($oldData[$idItem]->harga * (1 - $oldData[$idItem]->diskon / 100) * (1 - $oldInvoice->diskon / 100)),
                        'ref_id' => $oldData[$idItem]->id_penjualandetail
                    ]);
                    $oldData[$idItem]->refresh();
                    ProdukPersediaanDetail::create([
                        'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                        'tanggal' => $invoice->tanggal,
                        'keterangan' => "#{$invoice->transaksi_no} Update item penjualan invoice",
                        'stok_out' => $newData['qty'] ?? $oldData[$idItem]->qty,
                        'hargabeli' => (int)($oldData[$idItem]->hargabeli_avg),
                        'hargajual' => (int)(($newData['harga'] ?? $oldData[$idItem]->harga) * (1 - ($newData['diskon'] ?? $oldData[$idItem]->diskon) / 100) * (1 - $invoice->diskon / 100)),
                        'ref_id' => $oldData[$idItem]->id_penjualandetail
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    protected function deleteSalesInvoiceItem($idItem, $invoice, $oldData)
    {
        DB::beginTransaction();
        try {
            /* Check apakah item sudah di retur */
            if (DB::select('select exists(select 1 from toko_griyanaura.tr_penjualanreturdetail where id_penjualandetail = ?)', [$idItem])[0]->exists) {
                throw new SalesInvoiceException('Terdapat item yang sudah diretur');
            }
            /* Check apakah ketika di kurangi, total tagihan akan minus (alias yang dibayar lebih) */
            /* $diBayar = DB::select('select coalesce(sum(nominal),0) as jumlah from toko_griyanaura.tr_penjualanalokasipembayaran where id_penjualaninvoice = ?', [$invoice->id_penjualan])[0]->jumlah;
            $diRetur = DB::select('select coalesce(sum(grandtotal),0) as jumlah from toko_griyanaura.tr_penjualanretur where id_penjualan = ?', [$invoice->id_penjualan])[0]->jumlah; */
            if (DB::select('select toko_griyanaura.f_getsisatagihanpenjualan(?) as sisatagihan', [$invoice->transaksi_no])[0]->sisatagihan < $oldData[$idItem]->total * (1 - $invoice->diskon / 100)) {
                throw new SalesInvoiceException('Sisa tagihan tidak boleh minus');
            }
            if ($oldData[$idItem]->produkVarian->produk->in_stok == true) {
                /* Kurangi persediaan */
                $oldData[$idItem]->produkPersediaan->increment('stok', $oldData[$idItem]->qty);
                /* tambah persediaan detail (untuk riwayat keluar masuk stok) */
                ProdukPersediaanDetail::create([
                    'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                    'tanggal' => $invoice->tanggal,
                    'keterangan' => "#{$invoice->transaksi_no} Delete item penjualan invoice",
                    'stok_in' => $oldData[$idItem]->qty,
                    'hargabeli' => (int)($oldData[$idItem]->hargabeli),
                    'hargajual' => (int)($oldData[$idItem]->harga * (1 - $oldData[$idItem]->diskon / 100) * (1 - $invoice->diskon / 100)),
                    'ref_id' => $oldData[$idItem]->id_penjualandetail
                ]);
            }
            /* Delete item */
            PenjualanDetail::where('id_penjualandetail', $oldData[$idItem]->id_penjualandetail)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
