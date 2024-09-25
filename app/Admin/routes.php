<?php

use App\Admin\Actions\Grid\InlineDelete;
use App\Admin\Controllers\AjaxController;
use App\Admin\Controllers\CobaController;
use Illuminate\Support\Facades\Route;
use App\Admin\Controllers\ProdukController;
use App\Admin\Controllers\ProdukMutasiController;
use App\Admin\Controllers\ProdukPenyesuaianController;
use Encore\Admin\Actions\Action;
use Encore\Admin\Actions\RowAction;
use Encore\Admin\Facades\Admin;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
    'as'            => config('admin.route.prefix') . '.',
], function () {

    Route::get('/', 'HomeController@index')->name('home');
    Route::get('/coba-table', 'CobaController@cobaTable');
    Route::get('/product', function () {
        return 'HELLO';
    })->name('produk.list');
    Route::get('/product', [ProdukController::class, 'listProduk'])->name('produk.list');
    Route::get('/product/detail/{id}', [ProdukController::class, 'showProduk'])->name('produk.detail');
    Route::get('/product/create', [ProdukController::class, 'createProduk'])->name('produk.create');
    Route::get('/product/edit/{id}', [ProdukController::class, 'editProduk'])->name('produk.edit');
    Route::get('/product/edit/harga/{id}', [ProdukController::class, 'editHargaProduk'])->name('produk.edit.harga');
    Route::post('/product/store', [ProdukController::class, 'storeProduk'])->name('produk.store');
    Route::put('/product/update/{id}', [ProdukController::class, 'updateProduk'])->name('produk.update');
    Route::put('/product/update/harga/{id}', [ProdukController::class, 'updateProdukHarga'])->name('produk.update.harga');
    Route::match(['delete', 'post'], '/product/delete/{id}', [ProdukController::class, 'deleteProduk'])->name('produk.delete');
    Route::get('/warehouse-transfer', [ProdukMutasiController::class, 'listProdukMutasi'])->name('produk-mutasi.list');
    Route::get('/warehouse-transfer/create', [ProdukMutasiController::class, 'createProdukMutasi'])->name('produk-mutasi.create');
    Route::get('/warehouse-transfer/edit/{idPindahGudang}', [ProdukMutasiController::class, 'createProdukMutasiDetail'])->name('produk-mutasi.create.detail');
    Route::get('/warehouse-transfer/detail/{idPindahGudang}', [ProdukMutasiController::class, 'detailProdukMutasi'])->name('produk-mutasi.detail');
    Route::post('/warehouse-transfer/store', [ProdukMutasiController::class, 'storeProdukMutasi'])->name('produk-mutasi.store');
    Route::post('/warehouse-transfer/update/{idPindahGudang}', [ProdukMutasiController::class, 'storePindahGudangDetail'])->name('produk-mutasi.store.detail');
    Route::match(['delete', 'post'],'/warehouse-transfer/delete/{idPindahGudang}', [ProdukMutasiController::class, 'deleteProdukMutasi'])->name('produk-mutasi.delete');
    Route::get('/stock-adjustment/create', [ProdukPenyesuaianController::class, 'createProdukPenyesuaian'])->name('produk-penyesuaian.create');
    Route::get('/stock-adjustment/edit/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'createProdukPenyesuaianDetail'])->name('produk-penyesuaian.create.detail');
    Route::post('/stock-adjustment/store', [ProdukPenyesuaianController::class, 'storeProdukPenyesuaian'])->name('produk-penyesuaian.store');
    Route::get('/stock-adjustment/update/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'storeProdukPenyesuaianDetail'])->name('produk-penyesuaian.store.detail');
    Route::prefix('ajax')->group(function () {
        Route::get('/akun', [AjaxController::class, 'akun'])->name('ajax.akun');
        Route::get('/varians', [AjaxController::class, 'getVarians'])->name('ajax.varians');
        Route::get('/attribut-value/{idAttribut?}', [AjaxController::class, 'attributValue'])->name('ajax.attribut-value');
    });
});
