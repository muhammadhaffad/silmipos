<?php

use App\Admin\Actions\Grid\InlineDelete;
use App\Admin\Controllers\AjaxController;
use App\Admin\Controllers\CobaController;
use Illuminate\Support\Facades\Route;
use App\Admin\Controllers\ProdukController;
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
    Route::put('/product/update/{id}', [ProdukController::class, 'updateProduk'])->name('produk.update');
    Route::post('/product/delete/{id}', function () {
        return 'test';
    })->name('produk.delete');
    Route::post('/acdadc/{a}/cek', [CobaController::class, 'cobaHandle'])->name('test.handler');
    Route::prefix('ajax')->group(function () {
        Route::get('/akun', [AjaxController::class, 'akun'])->name('ajax.akun');
        Route::get('/attribut-value/{idAttribut?}', [AjaxController::class, 'attributValue'])->name('ajax.attribut-value');
    });
});
