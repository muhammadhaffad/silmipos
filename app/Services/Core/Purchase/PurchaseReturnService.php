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

    public function updateReturn($request, $idRetur)
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
            foreach ($request['pembelianDetail'] as $returItem) {
                if (isset($oldItem[$returItem['id_pembelianreturdetail']])) {
                    if ($returItem['qty_diretur'] > 0) {

                    } else {

                    }
                } else {
                    if ($returItem['qty_diretur'] > 0) {

                    }
                }
            }
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function storeReturnItem($return, $newData)
    {
        $pembelianDetail = PembelianDetail::select('*', DB::raw("qty - coalesce(sum(prd.qty),0) as sisaqty"))
            ->leftJoin('toko_griyanaura.tr_pembelianreturdetail as prd', 'toko_griyanaura.tr_pembeliandetail.id_pembeliandetail', 'prd.id_pembeliandetail')
            ->where('id_pembeliandetail', $newData['id_pembeliandetail'])
            ->groupBy('id_pembeliandetail')
            ->havingRaw('qty - coalesce(sum(prd.qty), 0) >= ?', [$newData['qty_diretur']])
            ->first();
        if (!$pembelianDetail) 
        {
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
                'total' => $pembelianDetail['harga']*$newData['qty_diretur']*(1-$pembelianDetail['diskon']/100.0),
                'totalraw' => $pembelianDetail['harga']*$newData['qty_diretur']
            ]);
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok -' . number($newData['qty_diretur']))
            ]);
            $dataPersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Store item pembelian invoice",
                'stok_out' => $newData['qty_diretur'],
                'hargabeli' => (int)($pembelianDetail['harga'] * (1 - $pembelianDetail['diskon'] / 100) * (1 - $return['diskon'] / 100)),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            return $pembelianReturDetail;
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function updateReturnItem($idItem, $return, $oldReturn, $newData, $oldData)
    {
        DB::beginTransaction();
        try {
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function deleteReturnItem($idItem, $return, $oldData)
    {

    }
}