<?php

namespace App\Services\Core\Purchase;

use App\Exceptions\PurchaseReturnException;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\PembelianPembayaran;
use App\Models\PembelianRetur;
use App\Models\PembelianReturAlokasiKembalianDana;
use App\Models\PembelianReturDetail;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Models\Transaksi;
use App\Services\Core\Jurnal\JurnalService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseReturnService
{
    use JurnalService;
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
                'id_transaksijenis' => 'pembelianretur',
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
            $pembelianRetur = PembelianRetur::with(['pembelianReturDetail.produkVarian.produk', 'pembelianReturDetail.produkPersediaan'])->find($idRetur);
            $pembelianRetur->tanggal = $request['tanggal'];
            $pembelianRetur->catatan = $request['catatan'];
            $pembelianRetur->save();
            $oldItem = $pembelianRetur->pembelianReturDetail->keyBy('id_pembelianreturdetail');
            $rawTotal = 0;
            foreach ($request['pembelianDetail'] as $returItem) {
                if (isset($oldItem[$returItem['id_pembelianreturdetail']])) {
                    if ($returItem['qty_diretur'] > 0) {
                        if ($oldItem[$returItem['id_pembelianreturdetail']]['qty'] != $returItem['qty_diretur']) {
                            $this->updateReturnItem($returItem['id_pembelianreturdetail'], $pembelianRetur, $returItem, $oldItem);
                        }
                    } else {
                        $this->deleteReturnItem($returItem['id_pembelianreturdetail'], $pembelianRetur, $oldItem);
                    }
                } else {
                    if ($returItem['qty_diretur'] > 0) {
                        $this->storeReturnItem($pembelianRetur, $returItem);
                    }
                }
            }
            $pembelianRetur->refresh();
            $pembelianRetur->load('pembelianReturDetail.produkPersediaan');
            $rawTotal = 0;
            foreach ($pembelianRetur->pembelianReturDetail as $item) {
                $rawTotal += $item->total;
            }
            $pembelianRetur->totalraw = $rawTotal;
            $pembelianRetur->grandtotal = $rawTotal * (1-$pembelianRetur->diskon/100);
            $pembelianRetur->save();
            $kembalianDana = DB::select('select toko_griyanaura.f_calckembaliandanareturpembelian(?) as kembaliandana', [$pembelianRetur->transaksi_no])[0]->kembaliandana;
            $this->deleteJurnal($pembelianRetur->id_transaksi);
            $detailTransaksi = [
                [
                    'kode_akun' => '2001',
                    'keterangan' => null,
                    'nominaldebit' => null,
                    'nominalkredit' => 0
                ]
            ];
            $total = 0;
            foreach ($pembelianRetur->pembelianReturDetail as $item) {
                $harga = (int)($item->qty * $item->harga * (1 - $item->diskon / 100) * (1 - $pembelianRetur->diskon / 100));
                $detailTransaksi[] = [
                    'kode_akun' => $item->produkVarian->produk->default_akunpersediaan,
                    'keterangan' => 'Retur pembelian ' . $item->produkVarian->varian,
                    'nominaldebit' => 0,
                    'nominalkredit' => (int)($item->qty*$item->produkPersediaan->hargabeli_avg),
                    'ref_id' => $item->id_pembelianreturdetail
                ];
                if ($harga - $item->qty*$item->produkPersediaan->hargabeli_avg > 0) {
                    $detailTransaksi[] = [
                        'kode_akun' => '8001', // pembulatan
                        'keterangan' => 'Retur pembelian ' . $item->produkVarian->varian,
                        'nominaldebit' => 0,
                        'nominalkredit' => (int)($harga - $item->qty*$item->produkPersediaan->hargabeli_avg),
                        'ref_id' => $item->id_pembelianreturdetail
                    ];
                } else if ($harga - $item->qty*$item->produkPersediaan->hargabeli_avg < 0) {
                    $detailTransaksi[] = [
                        'kode_akun' => '8001', // pembulatan
                        'keterangan' => 'Retur pembelian ' . $item->produkVarian->varian,
                        'nominaldebit' => (int)(\abs($harga - $item->qty*$item->produkPersediaan->hargabeli_avg)),
                        'nominalkredit' => 0,
                        'ref_id' => $item->id_pembelianreturdetail
                    ];
                }
                
                $total += $harga;
            }
            if (isset($detailTransaksi)) {
                $detailTransaksi[0]['nominaldebit'] = $total;
                $this->entryJurnal($pembelianRetur->id_transaksi, $detailTransaksi);
            }
            DB::commit();
            return $pembelianRetur;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function deleteReturn($idRetur)
    {
        DB::beginTransaction();
        try {
            $pembelianRetur = PembelianRetur::with('pembelianReturDetail.produkVarian.produk')->find($idRetur);
            $oldItem = $pembelianRetur->pembelianReturDetail->keyBy('id_pembelianreturdetail');
            foreach ($pembelianRetur->pembelianReturDetail as $returItem) {
                $this->deleteReturnItem($returItem['id_pembelianreturdetail'], $pembelianRetur, $oldItem);
            }
            $pembelianRetur->delete();
            $this->deleteJurnal($pembelianRetur->id_transaksi);
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    public function updateAllocate($idRetur, $request)
    {
        $rules = [
            'pembelianReturAlokasiKembalianDana.*.id_pembelianpembayaran' => 'nullable|numeric',
            'pembelianReturAlokasiKembalianDana.*.nominal' => 'required|numeric'
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        DB::beginTransaction();
        try {
            $pembelianRetur = PembelianRetur::with('pembelianReturAlokasiKembalianDana')->find($idRetur);
            $oldAlokasi = $pembelianRetur->pembelianReturAlokasiKembalianDana->keyBy('id_pembelianreturalokasikembaliandana');
            foreach ($request['pembelianReturAlokasiKembalianDana'] as $item) {
                if ($item['_remove_'] == 0) {
                    if (isset($oldAlokasi[$item['id_pembelianreturalokasikembaliandana']])) {
                        $newData = [
                            'nominal' => (int)$item['nominal']
                        ];
                        $this->updateAllocateRemainingReturnFund($item['id_pembelianreturalokasikembaliandana'], $pembelianRetur, $newData, $oldAlokasi);
                    } else {
                        $transaksi = Transaksi::create([
                            'transaksi_no' => null,
                            'id_transaksijenis' => 'pembelianreturalokasikembaliandana_dp',
                            'tanggal' => date('Y-m-d H:i:s'),
                            'inserted_by' => Admin::user()->username,
                            'updated_by' => Admin::user()->username
                        ]);
                        $newData = [
                            'id_transaksi' => $transaksi->id_transaksi,
                            'tanggal' => date('Y-m-d H:i:s'),
                            'id_pembelianpembayaran' => $item['id_pembelianpembayaran'],
                            'nominal' => (int)$item['nominal']
                        ];
                        $alokasi = $this->storeAllocateRemainingReturnFund($pembelianRetur, $newData);
                        $transaksi->transaksi_no = $alokasi->id_pembelianreturalokasikembaliandana;
                        $transaksi->save();
                    }
                } else {
                    $this->deleteAllocateRemainingReturnFund($item['id_pembelianreturalokasikembaliandana'], $pembelianRetur, $oldAlokasi);
                }
            }
            $pembelianRetur->refresh();
            foreach ($pembelianRetur->pembelianReturAlokasiKembalianDana as $alokasi) {
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
            return $pembelianRetur;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function storeReturnItem($return, $newData)
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
        if (DB::table('toko_griyanaura.tr_pembelianrefunddetail')->where('id_pembelianretur', $return->id_pembelianretur)->exists()) {
            throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
        }
        if (DB::table('toko_griyanaura.tr_pembelianreturalokasikembaliandana')->where('id_pembelianretur', $return->id_pembelianretur)->exists()) {
            throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
        }
        if (DB::table('toko_griyanaura.tr_pembelianalokasipembayaran')->where('id_pembelianinvoice', $return->id_pembelian)->where('tanggal', '>', $return->tanggal)->exists()) {
            throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
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
                'totalraw' => $pembelianDetail['harga'] * $newData['qty_diretur'],
                'id_gudang' => $pembelianDetail['id_gudang']
            ]);
            $persediaanProduk = ProdukPersediaan::addSelect(['hargabeli_avg' => ProdukPersediaanDetail::select(DB::raw('(sum(hargabeli*coalesce(stok_in,0) - hargabeli*coalesce(stok_out,0))/nullif(sum(coalesce(stok_in,0))-sum(coalesce(stok_out,0)),0))::int'))
                ->whereColumn('toko_griyanaura.ms_produkpersediaandetail.id_persediaan', 'toko_griyanaura.ms_produkpersediaan.id_persediaan')
            ])->where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok -' . number($newData['qty_diretur']))
            ]);
            $dataPersediaanDetail = ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Store item diretur",
                'stok_out' => $newData['qty_diretur'],
                'hargabeli' => (int)($persediaanProduk->hargabeli_avg),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            DB::commit();
            return $pembelianReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
    protected function updateReturnItem($idItem, $return, $newData, $oldData)
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
            if (DB::table('toko_griyanaura.tr_pembelianalokasipembayaran')->where('id_pembelianinvoice', $return->id_pembelian)->where('tanggal', '>', $return->tanggal)->exists()) {
                throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            $oldQty = $oldData[$idItem]->qty;
            $oldData[$idItem]->qty = $newData['qty_diretur'];
            $oldData[$idItem]->total = $pembelianDetail['harga'] * $newData['qty_diretur'] * (1 - $pembelianDetail['diskon'] / 100.0);
            $oldData[$idItem]->totalraw = $pembelianDetail['harga'] * $newData['qty_diretur'];
            $oldData[$idItem]->save();
            $persediaanProduk = ProdukPersediaan::addSelect(['hargabeli_avg' => ProdukPersediaanDetail::select(DB::raw('(sum(hargabeli*coalesce(stok_in,0) - hargabeli*coalesce(stok_out,0))/nullif(sum(coalesce(stok_in,0))-sum(coalesce(stok_out,0)),0))::int'))
                ->whereColumn('toko_griyanaura.ms_produkpersediaandetail.id_persediaan', 'toko_griyanaura.ms_produkpersediaan.id_persediaan')
            ])->where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok + ' . number($oldQty) . ' - ' . number($newData['qty_diretur']))
            ]);
            $hargaBeliTerakhir = ProdukPersediaanDetail::where('keterangan', 'ilike', '#' . $return->transaksi_no . '%')->where('ref_id', $oldData[$idItem]->id_pembelianreturdetail)->latest('id_persediaandetail')->first()->hargabeli;
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Update item diretur",
                'stok_in' => $oldQty,
                'hargabeli' => (int)($hargaBeliTerakhir),
                'ref_id' => $oldData[$idItem]->id_pembelianreturdetail
            ]);
            $persediaanProduk = ProdukPersediaan::addSelect(['hargabeli_avg' => ProdukPersediaanDetail::select(DB::raw('(sum(hargabeli*coalesce(stok_in,0) - hargabeli*coalesce(stok_out,0))/nullif(sum(coalesce(stok_in,0))-sum(coalesce(stok_out,0)),0))::int'))
                ->whereColumn('toko_griyanaura.ms_produkpersediaandetail.id_persediaan', 'toko_griyanaura.ms_produkpersediaan.id_persediaan')
            ])->where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Update item diretur",
                'stok_out' => $newData['qty_diretur'],
                'hargabeli' => (int)($persediaanProduk->hargabeli_avg),
                'ref_id' => $oldData[$idItem]->id_pembelianreturdetail
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
            if (DB::table('toko_griyanaura.tr_pembelianalokasipembayaran')->where('id_pembelianinvoice', $return->id_pembelian)->where('tanggal', '>', $return->tanggal)->exists()) {
                throw new PurchaseReturnException('Transaksi retur tidak dapat diubah, karena terkait dengan transaksi lain.');
            }
            $pembelianReturDetail = PembelianReturDetail::where('id_pembelianreturdetail', $idItem)->first();
            $pembelianReturDetail->delete();
            $persediaanProduk = ProdukPersediaan::where('kode_produkvarian', $pembelianDetail['kode_produkvarian'])->where('id_gudang', $pembelianDetail['id_gudang'])->first();
            $persediaanProduk->update([
                'stok' => DB::raw('stok + ' . number($oldData[$idItem]->qty))
            ]);
            $hargaBeliTerakhir = ProdukPersediaanDetail::where('keterangan', 'ilike', '#' . $return->transaksi_no . '%')->where('ref_id', $oldData[$idItem]->id_pembelianreturdetail)->latest('id_persediaandetail')->first()->hargabeli;
            ProdukPersediaanDetail::create([
                'id_persediaan' => $persediaanProduk->id_persediaan,
                'tanggal' => $return->tanggal,
                'keterangan' => "#{$return->transaksi_no} Hapus item diretur",
                'stok_in' => $oldData[$idItem]->qty,
                'hargabeli' => (int)($hargaBeliTerakhir),
                'ref_id' => $pembelianReturDetail->id_pembelianreturdetail
            ]);
            DB::commit();
            return $pembelianReturDetail;
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }

    protected function storeAllocateRemainingReturnFund($return, $newData)
    {
        DB::beginTransaction();
        try {
            $alokasiDP = DB::select('select coalesce(sum(nominal),0) as alokasidp from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianretur = ?', [$return->id_pembelianretur])[0]->alokasidp;
            $alokasiRefund = DB::select('select coalesce(sum(nominal),0) as alokasirefund from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianretur = ?', [$return->id_pembelianretur])[0]->alokasirefund;
            if ($return->kembaliandana - $alokasiDP - $alokasiRefund < $newData['nominal']) {
                throw new PurchaseReturnException('Nominal lebih dari sisa kembalian dana retur!, nominal yang dapat dialokasi : Rp' . \number_format($return->kembaliandana - $alokasiRefund - $alokasiDP, 0, ',', '.'));
            }
            $alokasiDanaRetur = PembelianReturAlokasiKembalianDana::create([
                'id_pembelianretur' => $return->id_pembelianretur,
                'id_pembelianpembayaran' => $newData['id_pembelianpembayaran'],
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
            $payment = PembelianPembayaran::find($oldData[$idItem]->id_pembelianpembayaran);
            $alokasiDP = DB::select('select coalesce(sum(nominal),0) as alokasidp from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianretur = ?', [$return->id_pembelianretur])[0]->alokasidp - $oldData[$idItem]->nominal;
            $alokasiRefund = DB::select('select coalesce(sum(nominal),0) as alokasirefund from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianretur = ?', [$return->id_pembelianretur])[0]->alokasirefund;
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaran(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran - $oldData[$idItem]->nominal;
            if ($return->kembaliandana - $alokasiRefund - $alokasiDP < $newData['nominal']) {
                throw new PurchaseReturnException('Nominal lebih dari sisa kembalian dana retur!, nominal yang dapat dialokasi : Rp' . \number_format($return->kembaliandana - $alokasiRefund - $alokasiDP, 0, ',', '.'));
            }
            if ($sisaPembayaran < 0) {
                throw new PurchaseReturnException('Sisa pembayaran tidak boleh minus!');
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
            $payment = PembelianPembayaran::find($oldData[$idItem]->id_pembelianpembayaran);
            $alokasiDP = DB::select('select coalesce(sum(nominal),0) as alokasidp from toko_griyanaura.tr_pembelianreturalokasikembaliandana where id_pembelianretur = ?', [$return->id_pembelianretur])[0]->alokasidp - $oldData[$idItem]->nominal;
            $alokasiRefund = DB::select('select coalesce(sum(nominal),0) as alokasirefund from toko_griyanaura.tr_pembelianrefunddetail where id_pembelianretur = ?', [$return->id_pembelianretur])[0]->alokasirefund;
            $sisaPembayaran = DB::select('select toko_griyanaura.f_getsisapembayaran(?) as sisapembayaran', [$payment->transaksi_no])[0]->sisapembayaran - $oldData[$idItem]->nominal;
            if ($return->kembaliandana - $alokasiRefund - $alokasiDP < 0) {
                throw new PurchaseReturnException('Nominal lebih dari sisa kembalian dana retur!, nominal yang dapat dialokasi : Rp' . \number_format($return->kembaliandana - $alokasiRefund - $alokasiDP, 0, ',', '.'));
            }
            if ($sisaPembayaran < 0) {
                throw new PurchaseReturnException('Sisa pembayaran tidak boleh minus!');
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
