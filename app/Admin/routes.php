<?php

use App\Admin\Actions\Grid\InlineDelete;
use App\Admin\Controllers\AjaxController;
use App\Admin\Controllers\CobaController;
use Illuminate\Support\Facades\Route;
use App\Admin\Controllers\ProdukController;
use App\Admin\Controllers\ProdukMutasiController;
use App\Admin\Controllers\ProdukPenyesuaianController;
use App\Admin\Controllers\PurchaseController;
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
    Route::prefix('/product')->group(function () {
        Route::get('/', [ProdukController::class, 'listProduk'])->name('produk.list');
        Route::get('/detail/{id}', [ProdukController::class, 'showProduk'])->name('produk.detail');
        Route::get('/create', [ProdukController::class, 'createProduk'])->name('produk.create');
        Route::get('/edit/{id}', [ProdukController::class, 'editProduk'])->name('produk.edit');
        Route::get('/edit/harga/{id}', [ProdukController::class, 'editHargaProduk'])->name('produk.edit.harga');
        Route::post('/store', [ProdukController::class, 'storeProduk'])->name('produk.store');
        Route::put('/update/{id}', [ProdukController::class, 'updateProduk'])->name('produk.update');
        Route::put('/update/harga/{id}', [ProdukController::class, 'updateProdukHarga'])->name('produk.update.harga');
        Route::match(['delete', 'post'], '/delete/{id}', [ProdukController::class, 'deleteProduk'])->name('produk.delete');
    });
    Route::prefix('/warehouse-transfer')->group(function () {
        Route::get('/', [ProdukMutasiController::class, 'listProdukMutasi'])->name('produk-mutasi.list');
        Route::get('/create', [ProdukMutasiController::class, 'createProdukMutasi'])->name('produk-mutasi.create');
        Route::get('/edit/{idPindahGudang}', [ProdukMutasiController::class, 'createProdukMutasiDetail'])->name('produk-mutasi.create.detail');
        Route::get('/detail/{idPindahGudang}', [ProdukMutasiController::class, 'detailProdukMutasi'])->name('produk-mutasi.detail');
        Route::post('/store', [ProdukMutasiController::class, 'storeProdukMutasi'])->name('produk-mutasi.store');
        Route::put('/validate/{idPindahGudang}', [ProdukMutasiController::class, 'validateProdukMutasi'])->name('produk-mutasi.validate');
        Route::post('/update/{idPindahGudang}', [ProdukMutasiController::class, 'storePindahGudangDetail'])->name('produk-mutasi.store.detail');
        Route::match(['delete', 'post'],'/delete/{idPindahGudang}', [ProdukMutasiController::class, 'deleteProdukMutasi'])->name('produk-mutasi.delete');
    });
    Route::prefix('/stock-adjustment')->group(function () {
        Route::get('/', [ProdukPenyesuaianController::class, 'listProdukPenyesuaian'])->name('produk-penyesuaian.list');
        Route::get('/create', [ProdukPenyesuaianController::class, 'createProdukPenyesuaian'])->name('produk-penyesuaian.create');
        Route::get('/detail/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'detailProdukPenyesuaian'])->name('produk-penyesuaian.detail');
        Route::get('/edit/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'createProdukPenyesuaianDetail'])->name('produk-penyesuaian.create.detail');
        Route::post('/store', [ProdukPenyesuaianController::class, 'storeProdukPenyesuaian'])->name('produk-penyesuaian.store');
        Route::put('/validate/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'validateProdukPenyesuaian'])->name('produk-penyesuaian.validate');
        Route::post('/update/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'storeProdukPenyesuaianDetail'])->name('produk-penyesuaian.store.detail');
        Route::match(['post', 'delete'],'/delete/{idPenyesuaianGudang}', [ProdukPenyesuaianController::class, 'deleteProdukPenyesuaian'])->name('produk-penyesuaian.delete');
    });
    Route::prefix('/purchase')->group(function () {
        Route::get('/order/create', [PurchaseController::class, 'createPurchaseOrder'])->name('purchase.order.create');
        Route::get('/order/detail/{idPembelian}', [PurchaseController::class, 'detailPurchaseOrder'])->name('purchase.order.detail');
        Route::get('/order/edit/{idPembelian}', [PurchaseController::class, 'editPurchaseOrder'])->name('purchase.order.edit');
        Route::post('/order/store', [PurchaseController::class, 'storePurchaseOrder'])->name('purchase.order.store');
        Route::post('/order/to-invoice/{idPembelian}', [PurchaseController::class, 'toInvoicePurchaseOrder'])->name('purchase.order.to-invoice');
        Route::put('/order/update/{idPembelian}', [PurchaseController::class, 'updatePurchaseOrder'])->name('purchase.order.update');
    });
    Route::prefix('ajax')->group(function () {
        Route::get('/akun', [AjaxController::class, 'akun'])->name('ajax.akun');
        Route::get('/varians', [AjaxController::class, 'getVarians'])->name('ajax.varians');
        Route::get('/attribut-value/{idAttribut?}', [AjaxController::class, 'attributValue'])->name('ajax.attribut-value');
        Route::get('/produk', [AjaxController::class, 'getProduk'])->name('ajax.produk');
        Route::get('/kontak', [AjaxController::class, 'getKontak'])->name('ajax.kontak');
        Route::get('/detail-produk', [AjaxController::class, 'getProdukDetail'])->name('ajax.produk-detail');
    });
});
