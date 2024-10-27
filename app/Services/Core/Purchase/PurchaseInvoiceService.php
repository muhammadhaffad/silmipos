<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchaseInvoiceException;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\ProdukVarianHarga;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceService
{
    use JurnalService;
    public function storePurchaseInvoice($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'tanggaltempo' => 'required|date_format:Y-m-d H:i:s',
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
            $noTransaksi = DB::select("select ('PI' || lpad(nextval('toko_griyanaura.tr_pembelian_invoice_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'pembelian_invoice',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $pembelianInvoice = Pembelian::create([
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
            foreach ($request['pembelianDetail'] as $key => $item) {
                $newData = [
                    'kode_produkvarian' => $item['kode_produkvarian'],
                    'harga' => (int)$item['harga'],
                    'qty' => $item['qty'],
                    'diskon' => $item['diskon'] ?: 0,
                    'id_gudang' => $request['id_gudang']
                ];
                $this->storePurchaseInvoiceItem($pembelianInvoice, $newData);
                $rawTotal += (int)($item['harga'] * $item['qty'] * (1 - ($item['diskon'] ?: 0) / 100));
            }
            $grandTotal = (int)($rawTotal * (1 - $request['diskon'] / 100));
            $pembelianInvoice->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal,
                'id_gudang' => $request['id_gudang']
            ]);
            $pembelianInvoice = Pembelian::with('pembelianDetail.produkVarian.produk')->find($pembelianInvoice->id_pembelian);
            $detailTransaksi = [];
            foreach ($pembelianInvoice->pembelianDetail as $item) {
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $pembelianInvoice->diskon / 100)),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_pembeliandetail
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
            DB::rollBack();
            throw $e;
        }
    }
    public function updatePurchaseInvoice($idPembelian, $request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_gudang' => 'required|numeric',
            'diskon' => 'nullable|numeric',
            'catatan' => 'nullable|string',
            'pembelianDetail' => 'required|array',
            'pembelianDetail.*.kode_produkvarian' => 'nullable|string',
            'pembelianDetail.*.qty' => 'required|numeric',
            'pembelianDetail.*.harga' => 'required|numeric',
            'pembelianDetail.*.diskon' => 'nullable|numeric',
            'pembelianDetail.*.id_pembeliandetail' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $pembelianOld = (new Pembelian())->with('pembelianDetail.produkPersediaan', 'pembelianDetail.produkVarian.produk')->where('id_pembelian', $idPembelian)->first();
            $pembelian = (new Pembelian())->with('pembelianDetail.produkPersediaan', 'pembelianDetail.produkVarian.produk')->where('id_pembelian', $idPembelian)->first();
            $pembelian->update([
                'id_kontak' => $request['id_kontak'],
                'tanggal' => $request['tanggal'],
                'id_gudang' => $request['id_gudang'],
                'diskon' => $request['diskon'],
                'catatan' => $request['catatan']
            ]);
            $pembelian->refresh();
            $oldItem = $pembelian->pembelianDetail->keyBy('id_pembeliandetail');

            foreach ($request['pembelianDetail'] as $key => $item) {
                if ($item['_remove_'] == 0) {
                    /* Jika tidak dihapus */
                    if (isset($oldItem[$item['id_pembeliandetail']])) {
                        /* Jika di-update */
                        $newData = [];
                        if ($item['qty'] != $oldItem[$item['id_pembeliandetail']]['qty'] or $item['harga'] != $oldItem[$item['id_pembeliandetail']]['harga'] or $item['diskon'] != $oldItem[$item['id_pembeliandetail']]['diskon'] or $pembelian['id_gudang'] != $request['id_gudang']) {
                            $newData['qty'] = $item['qty'];
                            $newData['harga'] = (int)$item['harga'];
                            $newData['diskon'] = $item['diskon'];
                            $newData['id_gudang'] = $request['id_gudang'];
                            $newData['totalraw'] = $newData['qty'] * $newData['harga'];
                            $newData['total'] = $newData['qty'] * $newData['harga'] * (1 - $newData['diskon'] / 100);
                        }
                        $this->updatePurchaseInvoiceItem($item['id_pembeliandetail'], $pembelian, $pembelianOld, $newData, $oldItem);
                    } else {
                        /* Jika ditambah baru */
                        $newData = [
                            'kode_produkvarian' => $item['kode_produkvarian'],
                            'harga' => (int)$item['harga'],
                            'qty' => $item['qty'],
                            'diskon' => $item['diskon'] ?: 0,
                            'id_gudang' => $request['id_gudang']
                        ];
                        $this->storePurchaseInvoiceItem($pembelian, $newData);
                    }
                } else {
                    /* Jika dihapus */
                    if (isset($oldItem[$item['id_pembeliandetail']])) {
                        $this->deletePurchaseInvoiceItem($item['id_pembeliandetail'], $pembelian, $oldItem);
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
            /* Delete pencatatan jurnal */
            $this->deleteJurnal($pembelian->id_transaksi);
            /* Pencatatan ulang jurnal */
            $pembelian = Pembelian::with('pembelianDetail.produkVarian.produk')->find($pembelian->id_pembelian);
            $detailTransaksi = [];
            $total = 0;
            foreach ($pembelian->pembelianDetail as $item) {
                $total += (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $pembelian->diskon / 100));
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $pembelian->diskon / 100)),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_pembeliandetail
                ];
            }
            if ($pembelian->grandtotal - $total > 0) {
                $detailTransaksi[] = [
                    'kode_akun' => '8001',
                    'keterangan' => null,
                    'nominaldebit' => $pembelian->grandtotal - $total,
                    'nominalkredit' => 0
                ];
            }
            $detailTransaksi[] = [
                'kode_akun' => '2001',
                'keterangan' => null,
                'nominaldebit' => 0,
                'nominalkredit' => $pembelian->grandtotal
            ];
            if (isset($detailTransaksi)) {
                $this->entryJurnal($pembelian->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $pembelian;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function deletePurchaseInvoice($idPembelian)
    {
        DB::beginTransaction();
        try {
            $pembelian = (new Pembelian())->with('pembelianDetail.produkPersediaan', 'pembelianDetail.produkVarian.produk')->where('id_pembelian', $idPembelian)->first();
            $oldItem = $pembelian->pembelianDetail->keyBy('id_pembeliandetail');
            foreach ($pembelian->pembelianDetail as $item) {
                $this->deletePurchaseInvoiceItem($item->id_pembeliandetail, $pembelian, $oldItem);
            }
            $this->deleteJurnal($pembelian->id_transaksi);
            $pembelian->delete();
            Transaksi::where('id_transaksi', $pembelian->id_transaksi)->delete();
            DB::commit();   
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function storePurchaseInvoiceItem($invoice, $newData)
    {
        DB::beginTransaction();
        try {
            $pembelianDetail = PembelianDetail::create([
                'id_pembelian' => $invoice->id_pembelian,
                'kode_produkvarian' => $newData['kode_produkvarian'],
                'harga' => (int)$newData['harga'],
                'qty' => $newData['qty'],
                'diskonjenis' => 'persen',
                'diskon' => $newData['diskon'] ?: 0,
                'total' => (int)($newData['harga'] * $newData['qty'] * (1 - ($newData['diskon'] ?: 0) / 100)),
                'totalraw' => (int)$newData['harga'] * $newData['qty'],
                'id_gudang' => $newData['id_gudang']
            ]);
            $produkVarian = ProdukVarian::with('produk')->where('kode_produkvarian', $newData['kode_produkvarian'])->first();
            if ($produkVarian->produk->in_stok == true) {
                $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $newData['kode_produkvarian'])->where('id_gudang', $newData['id_gudang'])->first();
                if (!$persediaanProduk) {
                    if (ProdukVarianHarga::where('kode_produkvarian', $newData['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $newData['id_gudang'])->first()) {
                        $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $newData['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', $newData['id_gudang'])->first()->id_produkvarianharga;
                    } else {
                        $defaultVarianHarga = ProdukVarianHarga::where('kode_produkvarian', $newData['kode_produkvarian'])->join('toko_griyanaura.ms_produkharga as ph', 'ph.id_produkharga', 'toko_griyanaura.ms_produkvarianharga.id_produkharga')->where('ph.id_varianharga', 1 /* Reguler */)->first()->id_produkvarianharga;
                    }
                    $persediaanProduk = ProdukPersediaan::create([
                        'id_gudang' => $newData['id_gudang'],
                        'kode_produkvarian' => $newData['kode_produkvarian'],
                        'stok' => 0,
                        'default_varianharga' => $defaultVarianHarga
                    ]);
                }
                $persediaanProduk->update([
                    'stok' => DB::raw('stok +' . number($newData['qty']))
                ]);
                $dataPersediaanDetail = ProdukPersediaanDetail::create([
                    'id_persediaan' => $persediaanProduk->id_persediaan,
                    'tanggal' => $invoice->tanggal,
                    'keterangan' => "#{$invoice->transaksi_no} Store item pembelian invoice",
                    'stok_in' => $newData['qty'],
                    'hargabeli' => (int)($newData['harga'] * (1 - $newData['diskon'] / 100) * (1 - $invoice['diskon'] / 100)),
                    'ref_id' => $pembelianDetail->id_pembeliandetail
                ]);
            }
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updatePurchaseInvoiceItem($idItem, $invoice, $oldInvoice, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            if (!empty($newData)) {
                $qtyTelahDiretur = DB::select('(select sum(qty) as jumlah from toko_griyanaura.tr_pembelianreturdetail where id_pembeliandetail = ?)', [$idItem])[0]->jumlah;
                if (DB::select('select exists(select 1 from toko_griyanaura.tr_pembelianreturdetail where id_pembeliandetail = ?)', [$idItem])[0]->exists) {
                    if ($newData['harga'] != $oldData[$idItem]->harga or $newData['diskon'] != $oldData[$idItem]->diskon) {
                        throw new PurchaseInvoiceException('Produk yang sudah diretur hanya boleh merubah jumlah saja');
                    }
                    if ($qtyTelahDiretur > $newData['qty']) {
                        throw new PurchaseInvoiceException('Jumlah produk kurang dari jumlah yang diretur');
                    }
                }
                /* $diBayar = DB::select('select coalesce(sum(nominal),0) as jumlah from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianinvoice = ?', [$invoice->id_pembelian])[0]->jumlah;
                $diRetur = DB::select('select coalesce(sum(grandtotal),0) as jumlah from toko_griyanaura.tr_pembelianretur where id_pembelian = ?', [$invoice->id_pembelian])[0]->jumlah; */
                if (DB::select('select toko_griyanaura.f_getsisatagihan(?) as sisatagihan', [$invoice->transaksi_no])[0]->sisatagihan < ($oldData[$idItem]->total - $newData['total']) * (1 - $invoice->diskon/100)) {
                    throw new PurchaseInvoiceException('Sisa tagihan tidak boleh minus');
                }
                PembelianDetail::where('id_pembeliandetail', $idItem)->update($newData);
            } 
            if ($oldData[$idItem]->produkVarian->produk->in_stok == true) {
                /* Kurangi / Tambah Persediaan */
                if (!empty($newData)) {
                    $selisih = $newData['qty'] - $oldData[$idItem]['qty'];
                    if ($selisih > 0) {
                        $oldData[$idItem]->produkPersediaan->increment('stok', $selisih);
                    } else if ($selisih < 0) {
                        $oldData[$idItem]->produkPersediaan->decrement('stok', abs($selisih));
                    }
                }
                /* Tambah persediaan detail (untuk riwayat persediaan) */
                if ($oldInvoice->diskon != $invoice->diskon or !empty($newData)) {
                    ProdukPersediaanDetail::create([
                        'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                        'tanggal' => $invoice->tanggal,
                        'keterangan' => "#{$invoice->transaksi_no} Update item pembelian invoice",
                        'stok_out' => $oldData[$idItem]->qty,
                        'hargabeli' => (int)($oldData[$idItem]->harga * (1 - $oldData[$idItem]->diskon / 100) * (1 - $oldInvoice->diskon / 100)),
                        'ref_id' => $oldData[$idItem]->id_pembeliandetail
                    ]);
                    ProdukPersediaanDetail::create([
                        'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                        'tanggal' => $invoice->tanggal,
                        'keterangan' => "#{$invoice->transaksi_no} Update item pembelian invoice",
                        'stok_in' => $newData['qty'] ?? $oldData[$idItem]->qty,
                        'hargabeli' => (int)(($newData['harga'] ?? $oldData[$idItem]->harga) * (1 - ($newData['diskon'] ?? $oldData[$idItem]->diskon) / 100) * (1 - $invoice->diskon / 100)),
                        'ref_id' => $oldData[$idItem]->id_pembeliandetail
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    protected function deletePurchaseInvoiceItem($idItem, $invoice, $oldData)
    {
        DB::beginTransaction();
        try {
            /* Check apakah item sudah di retur */
            if (DB::select('select exists(select 1 from toko_griyanaura.tr_pembelianreturdetail where id_pembeliandetail = ?)', [$idItem])[0]->exists) {
                throw new PurchaseInvoiceException('Terdapat item yang sudah diretur');
            }
            /* Check apakah ketika di kurangi, total tagihan akan minus (alias yang dibayar lebih) */
            /* $diBayar = DB::select('select coalesce(sum(nominal),0) as jumlah from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianinvoice = ?', [$invoice->id_pembelian])[0]->jumlah;
            $diRetur = DB::select('select coalesce(sum(grandtotal),0) as jumlah from toko_griyanaura.tr_pembelianretur where id_pembelian = ?', [$invoice->id_pembelian])[0]->jumlah; */
            if (DB::select('select toko_griyanaura.f_getsisatagihan(?) as sisatagihan', [$invoice->transaksi_no])[0]->sisatagihan < $oldData[$idItem]->total* (1 - $invoice->diskon/100)) {
                throw new PurchaseInvoiceException('Sisa tagihan tidak boleh minus');
            }
            if ($oldData[$idItem]->produkVarian->produk->in_stok == true) {
                /* Kurangi persediaan */
                $oldData[$idItem]->produkPersediaan->decrement('stok', abs($oldData[$idItem]->qty));
                /* tambah persediaan detail (untuk riwayat keluar masuk stok) */
                ProdukPersediaanDetail::create([
                    'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                    'tanggal' => $invoice->tanggal,
                    'keterangan' => "#{$invoice->transaksi_no} Delete item pembelian invoice",
                    'stok_out' => $oldData[$idItem]->qty,
                    'hargabeli' => (int)($oldData[$idItem]->harga * (1 - $oldData[$idItem]->diskon / 100) * (1 - $invoice->diskon / 100)),
                    'ref_id' => $oldData[$idItem]->id_pembeliandetail
                ]);
            }
            /* Delete item */
            PembelianDetail::where('id_pembeliandetail', $oldData[$idItem]->id_pembeliandetail)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
