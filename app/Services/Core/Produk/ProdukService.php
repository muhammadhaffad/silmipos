<?php
namespace App\Services\Core\Produk;

use App\Models\Produk;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProdukService {
    protected function generateCombinations($arrays) {
        $result = [[]]; // Mulai dengan array kosong
    
        foreach ($arrays as $array) {
            $temp = [];
            foreach ($result as $combination) {
                foreach ($array as $item) {
                    $temp[] = array_merge($combination, [$item]);
                }
            }
            $result = $temp; // Perbarui hasil dengan kombinasi baru
        }
    
        return $result;
    }
    public function storeProduk($request) {
        $rules = [
            'nama' => 'required|string|min:5',
            'default_unit' => 'required|string',
            'deskripsi' => 'nullable|string',
            'in_stok' => 'required',
            'minstok' => 'required_if_accepted:in_stok',
            'has_varian' => 'required',
            'default_akunpersediaan' => 'required|string',
            'default_akunpemasukan' => 'required|string',
            'default_akunbiaya' => 'required|string',
            'produkAttribut' => 'required',
            'produkAttribut.*.id_attribut' => 'required_if_accepted:has_varian',
            'produkAttribut.*.id_attributvalue' => 'required_if_accepted:has_varian|array',
            'produkVarian' => 'required',
            'produkVarian.*.kode_produkvarian_new' => 'nullable|string',
            'produkVarian.*.hargajual' => 'nullable|numeric',
            'produkVarian.*.default_hargabeli' => 'nullable|numeric',
            'produkVarian.*.minstok' => 'required_if_accepted:in_stok',
        ];
        $validator = Validator::make($request, $rules);
        $validator->validate();
        $jumlahVarian = 1;
        foreach ($request['produkAttribut'] as $attribut) {
            $jumlahVarian *= count($attribut['id_attributvalue']) - 1 ?: 1; 
        }
        if ($jumlahVarian != count($request['produkVarian'])) {
            throw new \Exception('Varian tidak valid!');
        }

        DB::beginTransaction();
        try {
            $produk = Produk::create([
                'nama' => $request['nama'],
                'deskripsi' => $request['deskripsi'],
                'in_stok' => $request['in_stok'] == 'on' ? true : false,
                'default_unit' => $request['default_unit'],
                'default_akunpersediaan' => $request['default_akunpersediaan'],
                'default_akunpemasukan' => $request['default_akunpemasukan'],
                'default_akunbiaya' => $request['default_akunbiaya'],
                'inserted_by' => Admin::user()->username,
                'updated_by' => Admin::user()->username
            ]);
            $idProdukHarga = DB::table('toko_griyanaura.ms_produkharga')->insertGetId([
                'id_produk' => $produk->id_produk,
                'id_varianharga' => 1 //Reguler
            ], 'id_produkharga');
            foreach ($request['produkAttribut'] as $key => $attribut) {
                if ($attribut['id_attribut'] != null) {
                    $request['produkAttribut'][$key]['id_produkattribut'] = DB::table('toko_griyanaura.ms_produkattribut')->insertGetId([
                        'id_produk' => $produk->id_produk,
                        'id_attribut' => $attribut['id_attribut']
                    ],'id_produkattribut');
                }
            }  
            $produkAttribut = [];
            foreach ($request['produkAttribut'] as $key => $attribut) {
                if ($attribut['id_attribut'] != null) {
                    foreach ($attribut['id_attributvalue'] as $attVal) {
                        if ($attVal != null) {
                            $produkAttribut[$key][] = [$attribut['id_attribut'] => $attVal];
                        }
                    }
                }
            }
            $produkVarianValue = $this->generateCombinations($produkAttribut);
            foreach ($request['produkVarian'] as $key => $item) {
                $data = [
                    'id_produk' => $produk->id_produk,
                    'minstok' => $item['minstok'],
                    'inserted_by' => Admin::user()->username,
                    'updated_by' => Admin::user()->username
                ];
                if ($item['kode_produkvarian_new'] != null) {
                    $data['kode_produkvarian'] = $item['kode_produkvarian_new'];
                }
                $kodeProdukVarian = DB::table('toko_griyanaura.ms_produkvarian')->insertGetId($data, 'kode_produkvarian');   
                if (!empty($produkVarianValue[$key])) {
                    foreach ($produkVarianValue[$key] as $attributValue) {
                        DB::table('toko_griyanaura.ms_produkattributvarian')->insert([
                            'kode_produkvarian' => $kodeProdukVarian,
                            'id_produkattribut' => array_key_first($attributValue),
                            'id_attributvalue' => $attributValue[array_key_first($attributValue)],
                            'inserted_by' => Admin::user()->username,
                            'updated_by' => Admin::user()->username
                        ]);
                    }
                } 
                $idProdukVarianHarga = DB::table('toko_griyanaura.ms_produkvarianharga')->insertGetId([
                    'kode_produkvarian' => $kodeProdukVarian,
                    'id_produkharga' => $idProdukHarga,
                    'hargajual' => (int)$item['hargajual'],
                    'hargabeli' => (int)$item['default_hargabeli'],
                    'inserted_by' => Admin::user()->username,
                    'updated_by' => Admin::user()->username
                ], 'id_produkvarianharga');
                if ($request['in_stok'] == 'on') {
                    DB::table('toko_griyanaura.ms_produkpersediaan')->insert([
                        'kode_produkvarian' => $kodeProdukVarian,
                        'id_gudang' => 1, // Gudang Pusat
                        'stok' => 0,
                        'default_varianharga' => $idProdukVarianHarga,
                        'inserted_by' => Admin::user()->username,
                        'updated_by' => Admin::user()->username
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function updateProduk($idProduk, $request) {
        $rules = [
            'nama' => 'required|string|min:5',
            'default_unit' => 'required|string',
            'deskripsi' => 'nullable|string',
            'produkAttribut' => 'array',
            'produkAttribut.*.id_attribut' => 'required|numeric',
            'produkAttribut.*.id_produkattribut' => 'nullable|numeric',
            'produkAttribut.*._remove_' => 'required',
            'default_akunpersediaan' => 'required|string',
            'default_akunpemasukan' => 'required|string',
            'default_akunbiaya' => 'required|string',
            'produkVarian.*.kode_produkvarian_new' => 'required_if:produkVarian.*._remove_,0|string',
            'produkVarian.*.produk_varian_harga\.0\.hargajual' => 'nullable|numeric',
            'produkVarian.*.produk_varian_harga\.0\.hargabeli' => 'nullable|numeric',
            'produkVarian.*.minstok' => 'nullable|numeric',
            'produkVarian.*.kode_produkvarian' => 'nullable|string',
            'produkVarian.*._remove_' => 'required|numeric',
        ];
        if (isset($request['produkAttribut'])) {
            foreach ($request['produkAttribut'] as $key => $attr) {
                if ($attr['_remove_'] == 0) {
                    $rules["produkVarian.*.{$key}"] = 'required_if:produkVarian.*._remove_,0';
                }
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
            if (isset($request['produkAttribut'])) {
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
                        if (isset($varian['minstok']) and $produkVarian[$key]->minstok != $varian['minstok']) {
                            $newValues['minstok'] = $varian['minstok'] ?: 0;
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
                        if (isset($request['produkAttribut'])) {
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
                            'minstok' => $varian['minstok'] ?: 0,
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