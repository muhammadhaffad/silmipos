<?php

namespace App\Services\Core\Sales;

use App\Exceptions\SalesReturnException;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\PenjualanPembayaran;
use App\Models\PenjualanRetur;
use App\Models\PenjualanReturAlokasiKembalianDana;
use App\Models\PenjualanReturDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesReturnService
{
    use JurnalService;
    public function storeReturn($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'id_penjualan' => 'required|numeric',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_kontak' => 'required|numeric',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $penjualan = Penjualan::where('id_penjualan', $request['id_penjualan'])->where('jenis', 'invoice')->where('id_kontak', $request['id_kontak'])->first();
            if (!$penjualan) {
                throw new SalesReturnException('Invoice penjualan tidak ditemukan!');
            }
            $noTransaksi = DB::select("select ('SRET' || lpad(nextval('toko_griyanaura.tr_penjualanretur_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'penjualanretur',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $penjualanRetur = PenjualanRetur::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'id_penjualan' => $request['id_penjualan'],
                'tanggal' => $request['tanggal'],
                'jenis' => 'invoice',
                'diskonjenis' => 'persen',
                'diskon' => $penjualan['diskon'],
                'grandtotal' => 0,
                'catatan' => $request['catatan'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            DB::commit();
            return $penjualanRetur;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updateReturn($idRetur, $request)
    {
        $rules = [
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'catatan' => 'nullable|string',
            'penjualanDetail' => 'array',
            'penjualanDetail.*.qty_diretur' => 'required|numeric',
            'penjualanDetail.*.id_penjualanreturdetail' => 'nullable|numeric',
            'penjualanDetail.*.id_penjualandetail' => 'required|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $penjualanRetur = PenjualanRetur::with(['penjualanReturDetail.produkVarian.produk', 'penjualanReturDetail.produkPersediaan'])->find($idRetur);
            $penjualanRetur->tanggal = $request['tanggal'];
            $penjualanRetur->catatan = $request['catatan'];
            $penjualanRetur->save();
            $oldItem = $penjualanRetur->penjualanReturDetail->keyBy('id_penjualanreturdetail');
            $rawTotal = 0;
            foreach ($request['penjualanDetail'] as $returItem) {
                if (isset($oldItem[$returItem['id_penjualanreturdetail']])) {
                    if ($returItem['qty_diretur'] > 0) {
                        if ($oldItem[$returItem['id_penjualanreturdetail']]['qty'] != $returItem['qty_diretur']) {
                            $this->updateReturnItem($returItem['id_penjualanreturdetail'], $penjualanRetur, $returItem, $oldItem);
                        }
                    } else {
                        $this->deleteReturnItem($returItem['id_penjualanreturdetail'], $penjualanRetur, $oldItem);
                    }
                } else {
                    if ($returItem['qty_diretur'] > 0) {
                        $this->storeReturnItem($penjualanRetur, $returItem);
                    }
                }
            }
            $penjualanRetur->refresh();
            $penjualanRetur->load('penjualanReturDetail.produkPersediaan');
            $rawTotal = 0;
            foreach ($penjualanRetur->penjualanReturDetail as $item) {
                $rawTotal += $item->total;
            }
            $penjualanRetur->totalraw = $rawTotal;
            $penjualanRetur->grandtotal = $rawTotal * (1-$penjualanRetur->diskon/100);
            $penjualanRetur->save();
            $kembalianDana = DB::select('select toko_griyanaura.f_calckembaliandanareturpenjualan(?) as kembaliandana', [$penjualanRetur->transaksi_no])[0]->kembaliandana;
            $this->deleteJurnal($penjualanRetur->id_transaksi);
            $detailTransaksi = [
                [
                    'kode_akun' => '4003',
                    'keterangan' => null,
                    'nominaldebit' => null,
                    'nominalkredit' => 0
                ]
            ];
            $total = 0;
            foreach ($penjualanRetur->penjualanReturDetail as $item) {
                $harga = (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $penjualanRetur->diskon / 100));
                $total += $harga;
            }
            $detailTransaksi[] = [
                'kode_akun' => '1201',
                'keterangan' => 'Retur penjualan ' . $item->produkVarian->varian,
                'nominaldebit' => 0,
                'nominalkredit' => (int)($total)
            ];
            foreach ($penjualanRetur->penjualanReturDetail as $item) {
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => 'Retur penjualan ' . $item->produkVarian->varian,
                    'nominaldebit' => (int)($item->qty*$item->hargabeli),
                    'nominalkredit' => 0,
                    'ref_id' => $item->id_penjualanreturdetail
                ];
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunbiaya,
                    'keterangan' => 'Retur penjualan ' . $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty*$item->hargabeli),
                    'ref_id' => $item->id_penjualanreturdetail
                ];
            }
            if (isset($detailTransaksi)) {
                $detailTransaksi[0]['nominaldebit'] = $total;
                $this->entryJurnal($penjualanRetur->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $penjualanRetur;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function deleteReturn($idRetur)
    {
        DB::beginTransaction();
        try {
            $penjualanRetur = PenjualanRetur::with('penjualanReturDetail.produkVarian.produk')->find($idRetur);
            $oldItem = $penjualanRetur->penjualanReturDetail->keyBy('id_penjualanreturdetail');
            foreach ($penjualanRetur->penjualanReturDetail as $returItem) {
                $this->deleteReturnItem($returItem['id_penjualanreturdetail'], $penjualanRetur, $oldItem);
            }
            $penjualanRetur->delete();
            $this->deleteJurnal($penjualanRetur->id_transaksi);
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function updateAllocate($idRetur, $request)
    {
        $rules = [
            'penjualanReturAlokasiKembalianDana.*.id_penjualanpembayaran' => 'nullable|numeric',
            'penjualanReturAlokasiKembalianDana.*.nominal' => 'required|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $penjualanRetur = PenjualanRetur::with('penjualanReturAlokasiKembalianDana')->find($idRetur);
            $oldAlokasi = $penjualanRetur->penjualanReturAlokasiKembalianDana->keyBy('id_penjualanreturalokasikembaliandana');
            foreach ($request['penjualanReturAlokasiKembalianDana'] as $item) {
                if ($item['_remove_'] == 0) {
                    if (isset($oldAlokasi[$item['id_penjualanreturalokasikembaliandana']])) {
                        $newData = [
                            'nominal' => (int)$item['nominal']
                        ];
                        $this->updateAllocateRemainingReturnFund($item['id_penjualanreturalokasikembaliandana'], $penjualanRetur, $newData, $oldAlokasi);
                    } else {
                        $transaksi = Transaksi::create([
                            'transaksi_no' => null,
                            'id_transaksijenis' => 'penjualanreturalokasikembaliandana_dp',
                            'tanggal' => date('Y-m-d H:i:s'),
                            'inserted_by' => Admin::user()->username,
                            'updated_by' => Admin::user()->username
                        ]);
                        $newData = [
                            'id_transaksi' => $transaksi->id_transaksi,
                            'tanggal' => date('Y-m-d H:i:s'),
                            'id_penjualanpembayaran' => $item['id_penjualanpembayaran'],
                            'nominal' => (int)$item['nominal']
                        ];
                        $alokasi = $this->storeAllocateRemainingReturnFund($penjualanRetur, $newData);
                        $transaksi->transaksi_no = $alokasi->id_penjualanreturalokasikembaliandana;
                        $transaksi->save();
                    }
                } else {
                    $this->deleteAllocateRemainingReturnFund($item['id_penjualanreturalokasikembaliandana'], $penjualanRetur, $oldAlokasi);
                }
            }
            $penjualanRetur->refresh();
            foreach ($penjualanRetur->penjualanReturAlokasiKembalianDana as $alokasi) {
                $this->deleteJurnal($alokasi->id_transaksi);
                $this->entryJurnal($alokasi->id_transaksi, [
                    [
                        'kode_akun' => '1410',
                        'keterangan' => 'Alokasi kembalian dana retur ke pembayaran uang muka',
                        'nominaldebit' => $alokasi->nominal,
                        'nominalkredit' => 0
                    ],
                    [
                        'kode_akun' => '2001',
                        'keterangan' => 'Alokasi kembalian dana retur ke pembayaran uang muka',
                        'nominaldebit' => 0,
                        'nominalkredit' => $alokasi->nominal
                    ],
                ]);
            }
            DB::commit();
            return $penjualanRetur;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function storeReturnItem($return, $newData)
    {
        $penjualanDetail = (new PenjualanDetail)->select('toko_griyanaura.tr_penjualandetail.*', DB::raw("toko_griyanaura.tr_penjualandetail.qty - coalesce(sum(prd.qty),0) as sisaqty"))
            ->leftJoin('toko_griyanaura.tr_penjualanreturdetail as prd', 'toko_griyanaura.tr_penjualandetail.id_penjualandetail', 'prd.id_penjualandetail')
            ->where('toko_griyanaura.tr_penjualandetail.id_penjualandetail', $newData['id_penjualandetail'])
            ->groupBy('toko_griyanaura.tr_penjualandetail.id_penjualandetail')
            ->havingRaw('toko_griyanaura.tr_penjualandetail.qty - coalesce(sum(prd.qty), 0) >= ?', [$newData['qty_diretur']])
            ->first();
        if (!$penjualanDetail) {
            throw new SalesReturnException('Produk tidak ditemukan atau jumlah yang diretur lebih besar dari penjualan');
        }
        if (DB::table('toko_griyanaura.tr_penjualanrefunddetail')->where('id_penjualanretur', $return->id_penjualanretur)->exists()) {
            throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
        }
        if (DB::table('toko_griyanaura.tr_penjualanreturalokasikembaliandana')->where('id_penjualanretur', $return->id_penjualanretur)->exists()) {
            throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
        }
        if (DB::table('toko_griyanaura.tr_penjualanalokasipembayaran')->where('id_penjualaninvoice', $return->id_penjualan)->where('tanggal', '>', $return->tanggal)->exists()) {
            throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
        }
        DB::beginTransaction();
        try {
            $penjualanDetail->load(['produkPersediaan']);
            $penjualanReturDetail = PenjualanReturDetail::create([
                'id_penjualanretur' => $return->id_penjualanretur,
                'id_penjualandetail' => $newData['id_penjualandetail'],
                'kode_produkvarian' => $penjualanDetail['kode_produkvarian'],
                'harga' => (int)$penjualanDetail['harga'],
                'qty' => $newData['qty_diretur'],
                'diskonjenis' => $penjualanDetail['diskonjenis'],
                'diskon' => $penjualanDetail['diskon'],
                'total' => $penjualanDetail['harga'] * $newData['qty_diretur'] * (1 - $penjualanDetail['diskon'] / 100.0),
                'totalraw' => $penjualanDetail['harga'] * $newData['qty_diretur'],
                'id_gudang' => $penjualanDetail['id_gudang'],
                'hargabeli' => $penjualanDetail->produkPersediaan->hargabeli_avg
            ]);
            $penjualanDetail->produkPersediaan->update([
                'stok' => DB::raw('stok +' . number($newData['qty_diretur']))
            ]);
            $dataPersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $penjualanDetail->produkPersediaan->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Store item diretur",
                'stok_in' => $newData['qty_diretur'],
                'hargajual' => (int)($penjualanDetail->harga),
                'hargabeli' => (int)($penjualanDetail->produkPersediaan->hargabeli_avg),
                'ref_id' => $penjualanReturDetail->id_penjualanreturdetail
            ]);
            DB::commit();
            return $penjualanReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateReturnItem($idItem, $return, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            $penjualanDetail = (new PenjualanDetail)->select('toko_griyanaura.tr_penjualandetail.*', DB::raw("toko_griyanaura.tr_penjualandetail.qty - coalesce(sum(prd.qty),0) + {$oldData[$idItem]->qty} as sisaqty"))
                ->leftJoin('toko_griyanaura.tr_penjualanreturdetail as prd', 'toko_griyanaura.tr_penjualandetail.id_penjualandetail', 'prd.id_penjualandetail')
                ->where('toko_griyanaura.tr_penjualandetail.id_penjualandetail', $newData['id_penjualandetail'])
                ->groupBy('toko_griyanaura.tr_penjualandetail.id_penjualandetail')
                ->havingRaw('toko_griyanaura.tr_penjualandetail.qty - coalesce(sum(prd.qty), 0) + ? >= ?', [$oldData[$idItem]->qty, $newData['qty_diretur']])
                ->first();
            if (!$penjualanDetail) {
                throw new SalesReturnException('Produk tidak ditemukan atau jumlah yang diretur lebih besar dari penjualan' . $newData['id_penjualandetail'] . ':' . $oldData[$idItem]->id_penjualanreturdetail . ':' . $newData['qty_diretur']);
            }
            if (DB::table('toko_griyanaura.tr_penjualanrefunddetail')->where('id_penjualanretur', $return->id_penjualanretur)->exists()) {
                throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            if (DB::table('toko_griyanaura.tr_penjualanreturalokasikembaliandana')->where('id_penjualanretur', $return->id_penjualanretur)->exists()) {
                throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            if (DB::table('toko_griyanaura.tr_penjualanalokasipembayaran')->where('id_penjualaninvoice', $return->id_penjualan)->where('tanggal', '>', $return->tanggal)->exists()) {
                throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            $oldQty = $oldData[$idItem]->qty;
            $oldData[$idItem]->qty = $newData['qty_diretur'];
            $oldData[$idItem]->total = $penjualanDetail['harga'] * $newData['qty_diretur'] * (1 - $penjualanDetail['diskon'] / 100.0);
            $oldData[$idItem]->totalraw = $penjualanDetail['harga'] * $newData['qty_diretur'];
            $oldData[$idItem]->save();
            $oldData[$idItem]->produkPersediaan->update([
                'stok' => DB::raw('stok - ' . number($oldQty) . ' + ' . number($newData['qty_diretur']))
            ]);
            ProdukPersediaanDetail::create([
                'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Update item diretur",
                'stok_out' => $oldQty,
                'hargajual' => (int)($oldData[$idItem]->harga),
                'hargabeli' => (int)($oldData[$idItem]->hargabeli),
                'ref_id' => $oldData[$idItem]->id_penjualanreturdetail
            ]);
            $oldData[$idItem]->refresh();
            $oldData[$idItem]->hargabeli = $oldData[$idItem]->produkPersediaan->hargabeli_avg;
            $oldData[$idItem]->save();
            ProdukPersediaanDetail::create([
                'id_persediaan' => $oldData[$idItem]->produkPersediaan->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Update item diretur",
                'stok_in' => $newData['qty_diretur'],
                'hargajual' => (int)($oldData[$idItem]->harga),
                'hargabeli' => (int)($oldData[$idItem]->hargabeli),
                'ref_id' => $oldData[$idItem]->id_penjualanreturdetail
            ]);
            DB::commit();
            return $oldData[$idItem];
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function deleteReturnItem($idItem, $return, $oldData) 
    {
        DB::beginTransaction();
        try {
            $penjualanDetail = (new PenjualanDetail)->select('toko_griyanaura.tr_penjualandetail.*')
                ->where('toko_griyanaura.tr_penjualandetail.id_penjualandetail', $oldData[$idItem]['id_penjualandetail'])
                ->first();
            if (!$penjualanDetail) {
                throw new SalesReturnException('Produk tidak ditemukan atau jumlah yang diretur lebih besar dari penjualan');
            }
            if (DB::table('toko_griyanaura.tr_penjualanrefunddetail')->where('id_penjualanretur', $return->id_penjualanretur)->exists()) {
                throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            if (DB::table('toko_griyanaura.tr_penjualanreturalokasikembaliandana')->where('id_penjualanretur', $return->id_penjualanretur)->exists()) {
                throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            if (DB::table('toko_griyanaura.tr_penjualanalokasipembayaran')->where('id_penjualaninvoice', $return->id_penjualan)->where('tanggal', '>', $return->tanggal)->exists()) {
                throw new SalesReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            $penjualanReturDetail = PenjualanReturDetail::where('id_penjualanreturdetail', $idItem)->first();
            $penjualanReturDetail->delete();
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $penjualanDetail['kode_produkvarian'])->where('id_gudang', $penjualanDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok - ' . number($oldData[$idItem]->qty))
            ]);
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Hapus item diretur",
                'stok_out' => $oldData[$idItem]->qty,
                'hargajual' => (int)($oldData[$idItem]->harga),
                'hargabeli' => (int)($oldData[$idItem]->hargabeli),
                'ref_id' => $penjualanReturDetail->id_penjualanreturdetail
            ]);
            DB::commit();
            return $penjualanReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function storeAllocateRemainingReturnFund($return, $newData)
    {
        DB::beginTransaction();
        try {
            $alokasiDP = DB::select('select coalesce(sum(nominal),0) as alokasidp from toko_griyanaura.tr_penjualanreturalokasikembaliandana where id_penjualanretur = ?', [$return->id_penjualanretur])[0]->alokasidp;
            $alokasiRefund = DB::select('select coalesce(sum(nominal),0) as alokasirefund from toko_griyanaura.tr_penjualanrefunddetail where id_penjualanretur = ?', [$return->id_penjualanretur])[0]->alokasirefund;
            if ($return->kembaliandana - $alokasiDP - $alokasiRefund < $newData['nominal']) {
                throw new SalesReturnException('Nominal lebih dari sisa kembalian dana retur!, nominal yang dapat dialokasi : Rp' . \number_format($return->kembaliandana - $alokasiRefund - $alokasiDP, 0, ',', '.'));
            }
            $alokasiDanaRetur = PenjualanReturAlokasiKembalianDana::create([
                'id_penjualanretur' => $return->id_penjualanretur,
                'id_penjualanpembayaran' => $newData['id_penjualanpembayaran'],
                'nominal' => (int)$newData['nominal'],
                'id_transaksi' => $newData['id_transaksi'],
                'tanggal' => $newData['tanggal']
            ]);
            DB::commit();
            return $alokasiDanaRetur;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateAllocateRemainingReturnFund($idItem, $return, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            $payment = PenjualanPembayaran::find($oldData[$idItem]->id_penjualanpembayaran);
            $alokasiDP = DB::select('select coalesce(sum(nominal),0) as alokasidp from toko_griyanaura.tr_penjualanreturalokasikembaliandana where id_penjualanretur = ?', [$return->id_penjualanretur])[0]->alokasidp - $oldData[$idItem]->nominal;
            $alokasiRefund = DB::select('select coalesce(sum(nominal),0) as alokasirefund from toko_griyanaura.tr_penjualanrefunddetail where id_penjualanretur = ?', [$return->id_penjualanretur])[0]->alokasirefund;
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaranpenjualan(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran - $oldData[$idItem]->nominal;
            if ($return->kembaliandana - $alokasiRefund - $alokasiDP < $newData['nominal']) {
                throw new SalesReturnException('Nominal lebih dari sisa kembalian dana retur!, nominal yang dapat dialokasi : Rp' . \number_format($return->kembaliandana - $alokasiRefund - $alokasiDP, 0, ',', '.'));
            }
            if ($sisaPembayaran < 0) {
                throw new SalesReturnException('Sisa pembayaran tidak boleh minus!');
            }
            if ($newData['nominal'] != $oldData[$idItem]['nominal']) 
            {
                $oldData[$idItem]->nominal = (int)$newData['nominal'];
            }
            if (isset($newData['tanggal']) and ($newData['tanggal'] != $oldData[$idItem]['tanggal']))
            {
                $oldData[$idItem]->tanggal = $newData['tanggal'];
            }
            $oldData[$idItem]->save();
            DB::commit();
            return $oldData;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function deleteAllocateRemainingReturnFund($idItem, $return, $oldData)
    {
        DB::beginTransaction();
        try {
            $payment = PenjualanPembayaran::find($oldData[$idItem]->id_penjualanpembayaran);
            $alokasiDP = DB::select('select coalesce(sum(nominal),0) as alokasidp from toko_griyanaura.tr_penjualanreturalokasikembaliandana where id_penjualanretur = ?', [$return->id_penjualanretur])[0]->alokasidp - $oldData[$idItem]->nominal;
            $alokasiRefund = DB::select('select coalesce(sum(nominal),0) as alokasirefund from toko_griyanaura.tr_penjualanrefunddetail where id_penjualanretur = ?', [$return->id_penjualanretur])[0]->alokasirefund;
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaranpenjualan(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran - $oldData[$idItem]->nominal;
            if ($return->kembaliandana - $alokasiRefund - $alokasiDP < 0) {
                throw new SalesReturnException('Nominal lebih dari sisa kembalian dana retur!, nominal yang dapat dialokasi : Rp' . \number_format($return->kembaliandana - $alokasiRefund - $alokasiDP, 0, ',', '.'));
            }
            if ($sisaPembayaran < 0) {
                throw new SalesReturnException('Sisa pembayaran tidak boleh minus!');
            }
            $oldData[$idItem]->delete();
            DB::commit();
            return $oldData;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
