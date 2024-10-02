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
                PembelianDetail::create([
                    'id_pembelian' => $pembelianInvoice->id_pembelian,
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
            $pembelianInvoice->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $grandTotal,
                'id_gudang' => $request['id_gudang']
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
                        'hargabeli' => (int)($item->harga * (1 - $item->diskon / 100) * (1 - $pembelianInvoice->diskon / 100)),
                        'ref_id' => $item->id_pembeliandetail
                    ]);
                }
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
            'pembelianDetail.*.kode_produkvarian' => 'required|string',
            'pembelianDetail.*.qty' => 'required|numeric',
            'pembelianDetail.*.harga' => 'required|numeric',
            'pembelianDetail.*.diskon' => 'nullable|numeric',
            'pembelianDetail.*.id_pembeliandetail' => 'nullable|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
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
                        if (!empty($newData)) {
                            $qtyTelahDiretur = DB::select('(select sum(qty) as jumlah from toko_griyanaura.tr_pembelianreturdetail where id_pembeliandetail = ?)', [$item['id_pembeliandetail']])[0]->jumlah;
                            if (DB::select('select exists(select 1 from toko_griyanaura.tr_pembelianreturdetail where id_pembeliandetail = ?)', [$item['id_pembeliandetail']])[0]->exists) {
                                if ($newData['harga'] != $oldItem[$item['id_pembeliandetail']]->harga or $newData['diskon'] != $oldItem[$item['id_pembeliandetail']]->diskon) {
                                    throw new PurchaseInvoiceException('Produk yang sudah diretur hanya boleh merubah jumlah saja');
                                }
                                if ($qtyTelahDiretur > $item['qty']) {
                                    throw new PurchaseInvoiceException('Jumlah produk kurang dari jumlah yang diretur');
                                }
                            }
                            $diBayar = DB::select('select coalesce(sum(nominal),0) as jumlah from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianinvoice = ?', [$pembelian->id_pembelian])[0]->jumlah;
                            $diRetur = DB::select('select coalesce(sum(grandtotal),0) as jumlah from toko_griyanaura.tr_pembelianretur where id_pembelian = ?', [$pembelian->id_pembelian])[0]->jumlah;
                            if ($diBayar > ($pembelian->grandtotal + ($newData['total'] - $oldItem[$item['id_pembeliandetail']]->total)) or $diRetur > ($pembelian->grandtotal + ($newData['total'] - $oldItem[$item['id_pembeliandetail']]->total))) {
                                throw new PurchaseInvoiceException('Sisa tagihan tidak boleh kurang dari 0');
                            }
                            /* Kurangi / Tambah Persediaan */
                            $selisih = $newData['qty'] - $oldItem[$item['id_pembeliandetail']]['qty'];
                            if ($selisih > 0) {
                                $oldItem[$item['id_pembeliandetail']]->produkPersediaan->increment('stok', $selisih);
                            } else if ($selisih < 0) {
                                $oldItem[$item['id_pembeliandetail']]->produkPersediaan->decrement('stok', $selisih);
                            }
                            /* Tambah persediaan detail (untuk riwayat persediaan) */
                            ProdukPersediaanDetail::create([
                                'id_persediaan' => $oldItem[$item['id_pembeliandetail']]->produkPersediaan->id_persediaan,
                                'tanggal' => $pembelian->tanggal,
                                'keterangan' => "#{$pembelian->transaksi_no} Update item pembelian invoice",
                                'stok_out' => $oldItem[$item['id_pembeliandetail']]->qty,
                                'hargabeli' => (int)($oldItem[$item['id_pembeliandetail']]->harga * (1 - $oldItem[$item['id_pembeliandetail']]->diskon / 100) * (1 - $pembelian->diskon / 100)),
                                'ref_id' => $oldItem[$item['id_pembeliandetail']]->id_pembeliandetail
                            ]);
                            ProdukPersediaanDetail::create([
                                'id_persediaan' => $oldItem[$item['id_pembeliandetail']]->produkPersediaan->id_persediaan,
                                'tanggal' => $pembelian->tanggal,
                                'keterangan' => "#{$pembelian->transaksi_no} Update item pembelian invoice",
                                'stok_in' => $newData['qty'],
                                'hargabeli' => (int)($newData['harga'] * (1 - $newData['diskon'] / 100) * (1 - $pembelian->diskon / 100)),
                                'ref_id' => $oldItem[$item['id_pembeliandetail']]->id_pembeliandetail
                            ]);
                            PembelianDetail::where('id_pembeliandetail', $item['id_pembeliandetail'])->update($newData);
                        }
                    } else {
                        /* Jika ditambah baru */
                        $pembelianDetail = PembelianDetail::create([
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
                        $produkVarian = ProdukVarian::with('produk')->where('kode_produkvarian', $item['kode_produkvarian'])->first();
                        if ($produkVarian->produk->in_stok == true) {
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
                            $persediaanProduk->update([
                                'stok' => DB::raw('stok +' . number($item['qty']))
                            ]);
                            $dataPersediaanDetail = ProdukPersediaanDetail::create([
                                'id_persediaan' => $persediaanProduk->id_persediaan,
                                'tanggal' => $pembelian->tanggal,
                                'keterangan' => "#{$pembelian->transaksi_no} Update item pembelian invoice",
                                'stok_in' => $item['qty'],
                                'hargabeli' => (int)($item->harga * (1 - $item->diskon / 100) * (1 - $pembelian->diskon / 100)),
                                'ref_id' => $pembelianDetail->id_pembeliandetail
                            ]);
                        }
                    }
                } else {
                    if (isset($oldItem[$item['id_pembeliandetail']])) {
                        /* Check apakah item sudah di retur */
                        if (DB::select('select exists(select 1 from toko_griyanaura.tr_pembelianreturdetail where id_pembeliandetail = ?)', [$item['id_pembeliandetail']])[0]->exists) {
                            throw new PurchaseInvoiceException('Terdapat item yang sudah diretur');
                        }
                        /* Check apakah ketika di kurangi, total tagihan akan minus (alias yang dibayar lebih) */
                        $diBayar = DB::select('select coalesce(sum(nominal),0) as jumlah from toko_griyanaura.tr_pembelianalokasipembayaran where id_pembelianinvoice = ?', [$pembelian->id_pembelian])[0]->jumlah;
                        $diRetur = DB::select('select coalesce(sum(grandtotal),0) as jumlah from toko_griyanaura.tr_pembelianretur where id_pembelian = ?', [$pembelian->id_pembelian])[0]->jumlah;
                        if ($diBayar > ($pembelian->grandtotal - $oldItem[$item['id_pembeliandetail']]->total) or $diRetur > ($pembelian->grandtotal - $oldItem[$item['id_pembeliandetail']]->total)) {
                            throw new PurchaseInvoiceException('Sisa tagihan tidak boleh kurang dari 0');
                        }
                        /* Kurangi persediaan */
                        $oldItem[$item['id_pembeliandetail']]->produkPersediaan->decrement($oldItem[$item['id_pembeliandetail']]->qty);
                        /* tambah persediaan detail (untuk riwayat keluar masuk stok) */
                        ProdukPersediaanDetail::create([
                            'id_persediaan' => $oldItem[$item['id_pembeliandetail']]->produkPersediaan->id_persediaan,
                            'tanggal' => $pembelian->tanggal,
                            'keterangan' => "#{$pembelian->transaksi_no} Delete item pembelian invoice",
                            'stok_out' => $oldItem[$item['id_pembeliandetail']]->qty,
                            'hargabeli' => (int)($oldItem[$item['id_pembeliandetail']]->harga * (1 - $oldItem[$item['id_pembeliandetail']]->diskon / 100) * (1 - $pembelian->diskon / 100)),
                            'ref_id' => $oldItem[$item['id_pembeliandetail']]->id_pembeliandetail
                        ]);
                        /* Delete item */
                        PembelianDetail::whereIn('id_pembeliandetail', $oldItem[$item['id_pembeliandetail']]->id_pembeliandetail)->delete();
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
            foreach ($pembelian->pembelianDetail as $item) {
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $pembelian->diskon / 100)),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_pembeliandetail
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
}
