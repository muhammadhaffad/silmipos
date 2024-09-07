<?php
namespace App\Services\Core\Produk;

use App\Models\Produk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProdukService {
    public function createProduk($request) {
        $validator = Validator::make($request, [
            'produk.nama' => 'required|string',
            'produk.deskripsi' => 'nullable|string',
            'produk.in_stok' => 'required|boolean',
            'produk.default_unit' => 'required|string',
            'produk.attribut' => 'required|',
            /* 'produk.default_pajakbeli' => 'required|numeric',
            'produk.default_pajakjual' => 'required|numeric', */
            'produk.default_akunpersediaan' => 'required|string', /* 1301 */
            'produk.default_akunpemasukan' => 'required|string', /* 4001 */
            'produk.default_akunbiaya' => 'required|string', /* 5002 */
            'produk_varian' => 'nullable|array',
            'produk_varian.*.attribut' => 'nullable|array',
            'produk_varian.*.attribut.*.attr' => 'required|in_array:produk.attribut.*',
            'produk_varian.*.attribut.*.attrval' => 'required|exists:pgsql.toko_griyanaura.lv_attributvalue,id_attributvalue',
            'produk_varian.*.kode_produkvarian' => 'required_if_accepted:produk.in_stok|string',
            'produk_varian.*.hargajual' => 'required_if_accepted:produk.in_stok|numeric',
            'produk_varian.*.default_hargabeli' => 'required_if_accepted:produk.in_stok|numeric',
            'produk_varian.*.pertanggal' => 'nullable|date',
            'produk_varian.*.minstok' => 'required_if_accepted:produk.in_stok|numeric',
        ]);
        $validator->validate();
        DB::beginTransaction();
        try {
            $produk = Produk::create($request['produk']);
            
            $produkAttribut = array_unique(array_merge(...array_map(function($item) { 
                return array_column($item['attribut'], 'attr');
            }, $request['produk_varian'])));
            foreach ($produkAttribut as &$produkAttr) {
                $produkAttr['id_produk'] = $produk['id_produk'];
            }
            DB::table('toko_griyanaura.ms_produkattribut')->insert($produkAttribut);
            $produkAttribut = array_map(function ($item) { return (array)$item; }, DB::table('toko_griyanaura.ms_produkattribut')->where('id_produk', $produk['id_produk'])->get()->toArray());
            $produkAttribut = array_column($produkAttribut, 'id_produkattribut', 'id_attribut');
            
            $produkVarian = $request['produk_varian'];
            $produkAttributVarian = array();
            foreach ($produkVarian as &$varian) {
                $varian['id_produk'] = $produk['id_produk'];
                $produkAttributVarian[]['kode_produkvarian'] = $varian['kode_produkvarian'];
                foreach ($varian['attribut'] as &$attr) {
                    $produkAttributVarian[]['id_produkattribut'] = $produkAttribut[(int)$attr['attr']];
                    $produkAttributVarian[]['id_attributvalue'] = $attr['attrval'];
                }
                unset($varian['attribut']);
            }

            DB::table('toko_griyanaura.ms_produkvarian')->insert($produkVarian);
            DB::table('toko_griyanaura.ms_produkattributvarian')->insert($produkAttributVarian);
            DB::commit();
        } catch (\Exception $th) {
            DB::rollBack();
            throw $th;
        }
    }
}