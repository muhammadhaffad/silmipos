<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchaseReturnException;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\PembelianRetur;
use App\Models\PembelianReturDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\Transaksi;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseReturnService
{
    public function storeReturn($request)
    {
        $rules = [
            'id_kontak' => 'required|numeric',
            'transaksi_no' => 'nullable|string',
            'id_pembelian' => 'required|numeric',
            'tanggal' => 'required|date_format:Y-m-d H:i:s',
            'id_kontak' => 'required|numeric',
            'catatan' => 'nullable|string'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $pembelian = Pembelian::where('id_pembelian', $request['id_pembelian'])->where('jenis', 'invoice')->where('id_kontak', $request['id_kontak'])->first();
            if (!$pembelian) {
                throw new PurchaseReturnException('Invoice pembelian tidak ditemukan!');
            }
            $noTransaksi = DB::select("select ('PRET' || lpad(nextval('toko_griyanaura.tr_pembelianretur_seq')::varchar, 6, '0')) as no_transaksi")[0]->no_transaksi;
            $transaksi = Transaksi::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksijenis' => 'pembelianpembayaran_tunai',
                'tanggal' => $request['tanggal'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $pembelianRetur = PembelianRetur::create([
                'transaksi_no' => $noTransaksi,
                'id_transaksi' => $transaksi->id_transaksi,
                'id_kontak' => $request['id_kontak'],
                'id_pembelian' => $request['id_pembelian'],
                'tanggal' => $request['tanggal'],
                'jenis' => 'invoice',
                'diskonjenis' => 'persen',
                'diskon' => $pembelian['diskon'],
                'grandtotal' => 0,
                'catatan' => $request['catatan'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            DB::commit();
            return $pembelianRetur;
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
            'pembelianDetail' => 'array',
            'pembelianDetail.*.qty_diretur' => 'required|numeric',
            'pembelianDetail.*.id_pembelianreturdetail' => 'nullable|numeric',
            'pembelianDetail.*.id_pembeliandetail' => 'required|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $pembelianRetur = PembelianRetur::with('pembelianReturDetail')->find($idRetur);
            $oldItem = $pembelianRetur->pembelianReturDetail->keyBy('id_pembelianreturdetail');
            $rawTotal = 0;
            foreach ($request['pembelianDetail'] as $returItem) {
                if (!$returItem['qty_diretur']) {
                    $returItem['qty_diretur'] = 0;
                }
                if (isset($oldItem[$returItem['id_pembelianreturdetail']])) {
                    if ($returItem['qty_diretur'] > 0 and ($oldItem[$returItem['id_pembelianreturdetail']]['qty'] != $returItem['qty_diretur'])) {
                        $result = $this->updateReturnItem($returItem['id_pembelianreturdetail'], $pembelianRetur, $returItem, $oldItem);
                    } else {
                        $this->deleteReturnItem($returItem['id_pembelianreturdetail'], $pembelianRetur, $oldItem);
                    }
                } else {
                    if ($returItem['qty_diretur'] > 0) {
                        $result = $this->storeReturnItem($pembelianRetur, $returItem);
                    }
                }
                $rawTotal += $result->total ?? 0;
            }
            DB::commit();
            $pembelianRetur->update([
                'totalraw' => $rawTotal,
                'grandtotal' => $rawTotal * (1-$pembelianRetur->diskon/100)
            ]);
            //TODO: calc kembalian retur
            $pembelianRetur->refresh();
            return $pembelianRetur;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function storeReturnItem($return, $newData)
    {
        $pembelianDetail = (new PembelianDetail)->setTable('toko_griyanaura.tr_pembeliandetail as x')->select('x.*', DB::raw("x.qty - coalesce(sum(prd.qty),0) as sisaqty"))
            ->leftJoin('toko_griyanaura.tr_pembelianreturdetail as prd', 'x.id_pembeliandetail', 'prd.id_pembeliandetail')
            ->where('x.id_pembeliandetail', $newData['id_pembeliandetail'])
            ->groupBy('x.id_pembeliandetail')
            ->havingRaw('x.qty - coalesce(sum(prd.qty), 0) >= ?', [$newData['qty_diretur']])
            ->first();
        if (!$pembelianDetail) {
            throw new PurchaseReturnException('Produk tidak ditemukan atau jumlah yang diretur lebih besar dari pembelian');
        }
        DB::beginTransaction();
        try {
            $pembelianReturDetail = PembelianReturDetail::create([
                'id_pembelianretur' => $return->id_pembelianretur,
                'id_pembeliandetail' => $newData['id_pembeliandetail'],
                'kode_produkvarian' => $pembelianDetail['kode_produkvarian'],
                'harga' => (int)$pembelianDetail['harga'],
                'qty' => $newData['qty_diretur'],
                'diskonjenis' => $pembelianDetail['diskonjenis'],
                'diskon' => $pembelianDetail['diskon'],
                'total' => $pembelianDetail['harga'] * $newData['qty_diretur'] * (1 - $pembelianDetail['diskon'] / 100.0),
                'totalraw' => $pembelianDetail['harga'] * $newData['qty_diretur']
            ]);
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok -' . number($newData['qty_diretur']))
            ]);
            $dataPersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Store item diretur",
                'stok_out' => $newData['qty_diretur'],
                'hargabeli' => (int)($pembelianReturDetail['harga'] * (1 - $pembelianReturDetail['diskon'] / 100) * (1 - $return['diskon'] / 100)),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            DB::commit();
            return $pembelianReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function updateReturnItem($idItem, $return, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            $pembelianDetail = (new PembelianDetail)->setTable('toko_griyanaura.tr_pembeliandetail as x')->select('x.*', DB::raw("x.qty - coalesce(sum(prd.qty),0) + {$oldData[$idItem]->qty} as sisaqty"))
                ->leftJoin('toko_griyanaura.tr_pembelianreturdetail as prd', 'x.id_pembeliandetail', 'prd.id_pembeliandetail')
                ->where('x.id_pembeliandetail', $newData['id_pembeliandetail'])
                ->groupBy('x.id_pembeliandetail')
                ->havingRaw('x.qty - coalesce(sum(prd.qty), 0) + ? >= ?', [$oldData[$idItem]->qty, $newData['qty_diretur']])
                ->first();
            if (!$pembelianDetail) {
                throw new PurchaseReturnException('Produk tidak ditemukan atau jumlah yang diretur lebih besar dari pembelian' . $newData['id_pembeliandetail'] . ':' . $oldData[$idItem]->id_pembelianreturdetail . ':' . $newData['qty_diretur']);
            }
            if (DB::table('toko_griyanaura.tr_pembelianrefunddetail')->where('id_pembelianretur', $return->id_pembelianretur)->exists()) {
                throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            if (DB::table('toko_griyanaura.tr_pembelianreturalokasikembaliandana')->where('id_pembelianretur', $return->id_pembelianretur)->exists()) {
                throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            $pembelianReturDetail = PembelianReturDetail::where('id_pembelianreturdetail', $idItem)->first();
            $pembelianReturDetail->update([
                'qty' => $newData['qty_diretur'],
                'total' => $pembelianDetail['harga'] * $newData['qty_diretur'] * (1 - $pembelianDetail['diskon'] / 100.0),
                'totalraw' => $pembelianDetail['harga'] * $newData['qty_diretur']
            ]);
            $pembelianReturDetail->refresh();
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok + ' . number($oldData[$idItem]->qty) . ' - ' . number($newData['qty_diretur']))
            ]);
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Update item diretur",
                'stok_in' => $oldData[$idItem]->qty,
                'hargabeli' => (int)($pembelianReturDetail['harga'] * (1 - $pembelianReturDetail['diskon'] / 100) * (1 - $return['diskon'] / 100)),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Update item diretur",
                'stok_out' => $newData['qty_diretur'],
                'hargabeli' => (int)($pembelianReturDetail['harga'] * (1 - $pembelianReturDetail['diskon'] / 100) * (1 - $return['diskon'] / 100)),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            DB::commit();
            return $pembelianReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function deleteReturnItem($idItem, $return, $oldData) 
    {
        DB::beginTransaction();
        try {
            $pembelianDetail = (new PembelianDetail)->setTable('toko_griyanaura.tr_pembeliandetail as x')->select('x.*')
                ->where('x.id_pembeliandetail', $oldData[$idItem]['id_pembeliandetail'])
                ->first();
            if (!$pembelianDetail) {
                throw new PurchaseReturnException('Produk tidak ditemukan atau jumlah yang diretur lebih besar dari pembelian');
            }
            if (DB::table('toko_griyanaura.tr_pembelianrefunddetail')->where('id_pembelianretur', $return->id_pembelianretur)->exists()) {
                throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            if (DB::table('toko_griyanaura.tr_pembelianreturalokasikembaliandana')->where('id_pembelianretur', $return->id_pembelianretur)->exists()) {
                throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            $pembelianReturDetail = PembelianReturDetail::where('id_pembelianreturdetail', $idItem)->first();
            $pembelianReturDetail->delete();
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok + ' . number($oldData[$idItem]->qty))
            ]);
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Hapus item diretur",
                'stok_in' => $oldData[$idItem]->qty,
                'hargabeli' => (int)($pembelianReturDetail['harga'] * (1 - $pembelianReturDetail['diskon'] / 100) * (1 - $return['diskon'] / 100)),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            DB::commit();
            return $pembelianReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
