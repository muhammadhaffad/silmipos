<?php
namespace App\Services\Core\Produk;

use App\Models\Produk;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\Auth;
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
    public function updateProduk($idProduk, $request) {
        $rules = [
            'nama' => 'required|string|min:5',
            'default_unit' => 'required|string',
            'deskripsi' => 'nullable|string',
            'produkAttribut' => 'required|array',
            'produkAttribut.*.id_attribut' => 'required|numeric',
            'produkAttribut.*.id_produkattribut' => 'nullable|numeric',
            'produkAttribut.*._remove_' => 'required',
            'default_akunpersediaan' => 'required|string',
            'default_akunpemasukan' => 'required|string',
            'default_akunbiaya' => 'required|string',
            'produkVarian.*.kode_produkvarian_new' => 'required_if:produkVarian.*._remove_,0|string',
            'produkVarian.*.produk_varian_harga\.0\.hargajual' => 'nullable|numeric',
            'produkVarian.*.produk_varian_harga\.0\.hargabeli' => 'nullable|numeric',
            'produkVarian.*.minstok' => 'required_if:produkVarian.*._remove_,0|numeric',
            'produkVarian.*.kode_produkvarian' => 'nullable|string',
            'produkVarian.*._remove_' => 'required|numeric',
        ];
        foreach ($request['produkAttribut'] as $key => $attr) {
            if ($attr['_remove_'] == 0) {
                $rules["produkVarian.*.{$key}"] = 'required_if:produkVarian.*._remove_,0';
            }
        }
        $validator = Validator::make($request, $rules);
        $validator->validate();

        DB::beginTransaction();
        try {
            /* Get data produk seblum di-update */
            $produk = Produk::where('id_produk', $idProduk)->with(['produkAttribut', 'produkVarian' => function ($relation) {
                $relation->with(['produkVarianHarga' => function ($q) {
                    $q->where('id_varianharga', '1');
                }]);
            }])->first();
            $produkVarian = $produk->produkVarian->keyBy('kode_produkvarian');
            $produkAttribut = $produk->produkAttribut->keyBy('id_produkattribut');
            /* Attribut varian */
            foreach ($request['produkAttribut'] as $key => $attribut) {
                if ($attribut['_remove_'] == 0) {
                    if (isset($produkAttribut[$key])) 
                    {
                        $newValues = [];
                        if ($produkAttribut[$key]->id_attribut != $attribut['id_attribut']) {
                            $newValues['id_attribut'] = $attribut['id_attribut'];
                        }
                        if (!empty($newValues)) {
                            $newValues['updated_at'] = date('Y-m-d H:i:s');
                            $newValues['updated_by'] = Admin::user()->username;
                            DB::table('toko_griyanaura.ms_produkattribut')->where('id_produkattribut', $attribut['id_produkattribut'])->update($newValues);
                        }
                    } else {
                        $request['produkAttribut'][$key]['id_produkattribut'] = DB::table('toko_griyanaura.ms_produkattribut')->insertGetId([
                            'id_attribut' => $attribut['id_attribut'],
                            'id_produk' => $idProduk
                        ], 'id_produkattribut');
                    }
                } else {
                    if (isset($produkAttribut[$key])) {
                        DB::table('toko_griyanaura.ms_produkattributvarian')->where('id_produkattribut', $key)->delete();
                        DB::table('toko_griyanaura.ms_produkattribut')->where('id_produkattribut', $key)->delete();
                    }
                }
            }
            /* Varian */
            foreach ($request['produkVarian'] as $key => $varian) {
                if ($varian['_remove_'] == 0) {
                    if (isset($produkVarian[$key])) {
                        $newValues = [];
                        $newValuesHarga = [];
                        if ($produkVarian[$key]->kode_produkvarian !== $varian['kode_produkvarian_new']) {
                            $newValues['kode_produkvarian'] = $varian['kode_produkvarian_new'];
                        }
                        if ($produkVarian[$key]->minstok != $varian['minstok']) {
                            $newValues['minstok'] = $varian['minstok'];
                        }
                        if ($produkVarian[$key]->produkVarianHarga->first()->hargajual != $varian['produk_varian_harga.0.hargajual']) {
                            $newValuesHarga['hargajual'] = (int)$varian['produk_varian_harga.0.hargajual'];
                        }
                        if ($produkVarian[$key]->produkVarianHarga->first()->hargabeli != $varian['produk_varian_harga.0.hargabeli']) {
                            $newValuesHarga['hargabeli'] = (int)$varian['produk_varian_harga.0.hargabeli'];
                        }
                        $oldVariansJson = json_decode($produkVarian[$key]->varian_id, true);
                        $oldVarians = [];
                        // Gabungkan elemen-elemen array ke dalam array baru
                        foreach ($oldVariansJson as $item) {
                            $oldVarians += $item;
                        }
                        foreach ($request['produkAttribut'] as $keyAttr => $attribut) {
                            if ($attribut['_remove_'] == 0) {
                                if (isset($oldVarians[$keyAttr])) {
                                    $newValues = [];
                                    if ($oldVarians[$keyAttr] != $varian[$keyAttr]) {
                                        $newValues['id_attributvalue'] = $varian[$keyAttr];
                                    }
                                    if (!empty($newValues)) {
                                        $newValues['updated_at'] = date('Y-m-d H:i:s');
                                        $newValues['updated_by'] = Admin::user()->username;
                                        DB::table('toko_griyanaura.ms_produkattributvarian')->where('kode_produkvarian', $varian['kode_produkvarian'])->where('id_produkattribut', $keyAttr)->update($newValues);
                                    }
                                } else {
                                    // dump($oldVarians, $request['produkAttribut'], $varian, $attribut);
                                    // dump('===========');
                                    DB::table('toko_griyanaura.ms_produkattributvarian')->insert([
                                        'kode_produkvarian' => $varian['kode_produkvarian'],
                                        'id_produkattribut' => $attribut['id_produkattribut'],
                                        'id_attributvalue' => $varian[$keyAttr],
                                        'inserted_by' => Admin::user()->username,
                                        'updated_by' => Admin::user()->username
                                    ]);
                                }
                            }
                        }
                        if (!empty($newValuesHarga)) {
                            $newValuesHarga['updated_at'] = date('Y-m-d H:i:s');
                            $newValuesHarga['updated_by'] = Admin::user()->username;
                            $idProdukHarga = DB::table('toko_griyanaura.ms_produkharga')->where(['id_produk'=> $idProduk, 'id_varianharga' => 1])->first()->id_produkharga;
                            DB::table('toko_griyanaura.ms_produkvarianharga')->where('kode_produkvarian', $varian['kode_produkvarian'])->where('id_produkharga', $idProdukHarga)->update($newValuesHarga);
                        }
                        if (!empty($newValues)) {
                            $newValues['updated_at'] = date('Y-m-d H:i:s');
                            $newValues['updated_by'] = Admin::user()->username;
                            DB::table('toko_griyanaura.ms_produkvarian')->where('kode_produkvarian', $varian['kode_produkvarian'])->update($newValues);
                        }
                    } else {
                        DB::table('toko_griyanaura.ms_produkvarian')->insert([
                            'kode_produkvarian' => $varian['kode_produkvarian_new'],
                            'id_produk' => $idProduk,
                            'minstok' => $varian['minstok'],
                            'inserted_by' => Admin::user()->username,
                            'updated_by' => Admin::user()->username
                        ]);
                        $idProdukHarga = DB::table('toko_griyanaura.ms_produkharga')->where(['id_produk'=> $idProduk, 'id_varianharga' => 1])->first()->id_produkharga;
                        $idGudang = DB::table('toko_griyanaura.lv_gudang')->orderBy('id_gudang')->first()->id_gudang;
                        $idVarianHarga = DB::table('toko_griyanaura.ms_produkvarianharga')->insertGetId([
                            'kode_produkvarian' => $varian['kode_produkvarian_new'],
                            'hargajual' => (int)$varian['produk_varian_harga.0.hargajual'] ?: 0,
                            'hargabeli' => (int)$varian['produk_varian_harga.0.hargabeli'] ?: 0,
                            'inserted_by' => Admin::user()->username,
                            'updated_by' => Admin::user()->username,
                            'id_produkharga' => $idProdukHarga
                        ], 'id_produkvarianharga');
                        DB::table('toko_griyanaura.ms_produkpersediaan')->insert([
                            'id_gudang' => $idGudang,
                            'kode_produkvarian' => $varian['kode_produkvarian_new'],
                            'stok' => 0,
                            'default_varianharga' => $idVarianHarga,
                            'inserted_by' => Admin::user()->username,
                            'updated_by' => Admin::user()->username
                        ]);
                        foreach ($request['produkAttribut'] as $keyAttr => $attribut) {
                            if ($attribut['_remove_'] == 0) {
                                DB::table('toko_griyanaura.ms_produkattributvarian')->insert([
                                    'kode_produkvarian' => $varian['kode_produkvarian_new'],
                                    'id_produkattribut' => $attribut['id_produkattribut'],
                                    'id_attributvalue' => $varian[$keyAttr],
                                    'inserted_by' => Admin::user()->username,
                                    'updated_by' => Admin::user()->username
                                ]);
                            }
                        }
                    }
                } else {
                    if (isset($produkVarian[$key])) {
                        DB::table('toko_griyanaura.ms_produkattributvarian')->where('kode_produkvarian', $produkVarian[$key]->kode_produkvarian)->delete();
                        DB::table('toko_griyanaura.ms_produkpersediaan')->where('kode_produkvarian', $produkVarian[$key]->kode_produkvarian)->delete();
                        DB::table('toko_griyanaura.ms_produkvarianharga')->where('kode_produkvarian', $produkVarian[$key]->kode_produkvarian)->delete();
                        DB::table('toko_griyanaura.ms_produkvarian')->where('kode_produkvarian', $produkVarian[$key]->kode_produkvarian)->delete();
                    }
                }
            }
            $newValues = [];
            if ($produk->nama != $request['nama']) {
                $newValues['nama'] = $request['nama'];
            }
            if ($produk->default_unitx != $request['default_unit']) {
                $newValues['default_unit'] = $request['default_unit'];
            }
            if ($produk->deskripsi != $request['deskripsi']) {
                $newValues['deskripsi'] = $request['deskripsi'];
            }
            if ($produk->default_akunpersediaan != $request['default_akunpersediaan']) {
                $newValues['default_akunpersediaan'] = $request['default_akunpersediaan'];
            }
            if ($produk->default_akunpemasukan != $request['default_akunpemasukan']) {
                $newValues['default_akunpemasukan'] = $request['default_akunpemasukan'];
            }
            if ($produk->default_akunbiaya != $request['default_akunbiaya']) {
                $newValues['default_akunbiaya'] = $request['default_akunbiaya'];
            }
            if (!empty($newValues)) {
                $newValues['updated_at'] = date('Y-m-d H:i:s');
                $newValues['updated_by'] = Auth::user()->username;
                DB::table('toko_griyanaura.ms_produk')->where('id_produk', $idProduk)->update($newValues);
            }
            DB::commit();
        } catch (\Exception $e) {
            throw $e;
            DB::rollBack();
        }

    }
}