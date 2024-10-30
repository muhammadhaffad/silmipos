<?php

use App\Admin\Actions\Grid\InlineDelete;
use App\Admin\Controllers\AjaxController;
use App\Admin\Controllers\CobaController;
use Illuminate\Support\Facades\Route;
use App\Admin\Controllers\ProdukController;
use App\Admin\Controllers\ProdukMutasiController;
use App\Admin\Controllers\ProdukPenyesuaianController;
use App\Admin\Controllers\PurchaseDownPaymentController;
use App\Admin\Controllers\PurchaseInvoiceController;
use App\Admin\Controllers\PurchaseOrderController;
use App\Admin\Controllers\PurchasePaymentController;
use App\Admin\Controllers\PurchaseRefundPaymentController;
use App\Admin\Controllers\PurchaseReturnController;
use App\Admin\Controllers\SalesDownPaymentController;
use App\Admin\Controllers\SalesInvoiceController;
use App\Admin\Controllers\SalesOrderController;
use App\Admin\Controllers\SalesPaymentController;
use App\Admin\Controllers\SalesRefundPaymentController;
use App\Admin\Controllers\SalesReturnController;
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
        Route::prefix('/order')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'listPurchaseOrder'])->name('purchase.order.list');
            Route::get('/create', [PurchaseOrderController::class, 'createPurchaseOrder'])->name('purchase.order.create');
            Route::get('/detail/{idPembelian}', [PurchaseOrderController::class, 'detailPurchaseOrder'])->name('purchase.order.detail');
            Route::get('/edit/{idPembelian}', [PurchaseOrderController::class, 'editPurchaseOrder'])->name('purchase.order.edit');
            Route::get('/to-invoice/{idPembelian}', [PurchaseOrderController::class, 'toInvoicePurchaseOrder'])->name('purchase.order.to-invoice');
            Route::post('/store', [PurchaseOrderController::class, 'storePurchaseOrder'])->name('purchase.order.store');
            Route::post('/to-invoice/store/{idPembelian}', [PurchaseOrderController::class, 'storeToInvoicePurchaseOrder'])->name('purchase.order.to-invoice.store');
            Route::put('/update/{idPembelian}', [PurchaseOrderController::class, 'updatePurchaseOrder'])->name('purchase.order.update');
            Route::match(['delete', 'post'], '/delete/{idPembelian}', [PurchaseOrderController::class, 'deletePurchaseOrder'])->name('purchase.order.delete');
        });
        Route::prefix('/invoice')->group(function () {
            Route::get('/', [PurchaseInvoiceController::class, 'listPurchaseInvoice'])->name('purchase.invoice.list');
            Route::get('/create', [PurchaseInvoiceController::class, 'createPurchaseInvoice'])->name('purchase.invoice.create');
            Route::get('/edit/{idPembelian}', [PurchaseInvoiceController::class, 'editPurchaseInvoice'])->name('purchase.invoice.edit');
            Route::get('/detail/{idPembelian}', [PurchaseInvoiceController::class, 'detailPurchaseInvoice'])->name('purchase.invoice.detail');

            Route::post('/store', [PurchaseInvoiceController::class, 'storePurchaseInvoice'])->name('purchase.invoice.store');
            Route::put('/update/{idPembelian}', [PurchaseInvoiceController::class, 'updatePurchaseInvoice'])->name('purchase.invoice.update');
            Route::match(['delete', 'post'],'/delete/{idPembelian}', [PurchaseInvoiceController::class, 'deletePurchaseInvoice'])->name('purchase.invoice.delete');
        });
        Route::prefix('/down-payment')->group(function () {
            Route::get('/', [PurchaseDownPaymentController::class, 'listPayment'])->name('purchase.down-payment.list');
            Route::get('/create', [PurchaseDownPaymentController::class, 'createPayment'])->name('purchase.down-payment.create');
            Route::get('/detail/{idPembayaran}', [PurchaseDownPaymentController::class, 'detailPayment'])->name('purchase.down-payment.detail');
            Route::get('/edit/{idPembayaran}', [PurchaseDownPaymentController::class, 'editPayment'])->name('purchase.down-payment.edit');
            
            Route::post('/store', [PurchaseDownPaymentController::class, 'storePayment'])->name('purchase.down-payment.store');
            Route::put('/update/{idPembayaran}', [PurchaseDownPaymentController::class, 'updatePayment'])->name('purchase.down-payment.update');
            Route::match(['delete', 'post'], '/delete/{idPembayaran}', [PurchaseDownPaymentController::class, 'deletePayment'])->name('purchase.down-payment.delete');
        });
        Route::prefix('/payment')->group(function () {
            Route::get('/', [PurchasePaymentController::class, 'listPayment'])->name('purchase.payment.list');
            Route::get('/create', [PurchasePaymentController::class, 'createPayment'])->name('purchase.payment.create');
            Route::get('/detail/{idPembayaran}', [PurchasePaymentController::class, 'detailPayment'])->name('purchase.payment.detail');
            Route::get('/edit/{idPembayaran}', [PurchasePaymentController::class, 'editPayment'])->name('purchase.payment.edit');
            
            Route::post('/store', [PurchasePaymentController::class, 'storePayment'])->name('purchase.payment.store');
            Route::put('/update/{idPembayaran}', [PurchasePaymentController::class, 'updatePayment'])->name('purchase.payment.update');
            Route::match(['delete', 'post'], '/delete/{idPembayaran}', [PurchasePaymentController::class, 'deletePayment'])->name('purchase.payment.delete');
        });
        Route::prefix('/return')->group(function () {
            Route::get('/', [PurchaseReturnController::class, 'listReturn'])->name('purchase.return.list');
            Route::get('/create', [PurchaseReturnController::class, 'createReturn'])->name('purchase.return.create');
            Route::get('/edit/{idRetur}', [PurchaseReturnController::class, 'editReturn'])->name('purchase.return.edit');
            Route::get('/detail/{idRetur}', [PurchaseReturnController::class, 'detailReturn'])->name('purchase.return.detail');

            Route::post('/store', [PurchaseReturnController::class, 'storeReturn'])->name('purchase.return.store');
            Route::put('/update/{idRetur}', [PurchaseReturnController::class, 'updateReturn'])->name('purchase.return.update');
            Route::match(['post', 'delete'], '/delete/{idRetur}', [PurchaseReturnController::class, 'deleteReturn'])->name('purchase.return.delete');

            Route::put('/update-allocate/{idRetur}', [PurchaseReturnController::class, 'updateAllocate'])->name('purchase.return.update-allocate');
        });
        Route::prefix('/refund')->group(function () {
            Route::get('/', [PurchaseRefundPaymentController::class, 'listRefund'])->name('purchase.refund.list');
            Route::get('/create', [PurchaseRefundPaymentController::class, 'createRefund'])->name('purchase.refund.create');
            Route::get('/edit/{idRefund}', [PurchaseRefundPaymentController::class, 'editRefund'])->name('purchase.refund.edit');
            Route::get('/detail/{idRefund}', [PurchaseRefundPaymentController::class, 'detailRefund'])->name('purchase.refund.detail');

            Route::post('/store', [PurchaseRefundPaymentController::class, 'storeRefund'])->name('purchase.refund.store');
            Route::put('/update/{idRefund}', [PurchaseRefundPaymentController::class, 'updateRefund'])->name('purchase.refund.update');
            Route::match(['post', 'delete'], '/delete/{idRefund}', [PurchaseRefundPaymentController::class, 'deleteRefund'])->name('purchase.refund.delete');
        });
    });
    Route::prefix('/sales')->group(function () {
        Route::prefix('/order')->group(function () {
            Route::get('/create', [SalesOrderController::class, 'createSalesOrder'])->name('sales.order.create');
            Route::get('/detail/{idPenjualan}', [SalesOrderController::class, 'detailSalesOrder'])->name('sales.order.detail');
            Route::get('/edit/{idPenjualan}', [SalesOrderController::class, 'editSalesOrder'])->name('sales.order.edit');
            Route::get('/to-invoice/{idPenjualan}', [SalesOrderController::class, 'toInvoiceSalesOrder'])->name('sales.order.to-invoice');
            Route::post('/store', [SalesOrderController::class, 'storeSalesOrder'])->name('sales.order.store');
            Route::post('/to-invoice/store/{idPenjualan}', [SalesOrderController::class, 'storeToInvoiceSalesOrder'])->name('sales.order.to-invoice.store');
            Route::put('/update/{idPenjualan}', [SalesOrderController::class, 'updateSalesOrder'])->name('sales.order.update');
            Route::match(['delete', 'post'], '/delete/{idPenjualan}', [SalesOrderController::class, 'deleteSalesOrder'])->name('sales.order.delete');
        });
        Route::prefix('/invoice')->group(function () {
            Route::get('/create', [SalesInvoiceController::class, 'createSalesInvoice'])->name('sales.invoice.create');
            Route::get('/detail/{idPenjualan}', [SalesInvoiceController::class, 'detailSalesInvoice'])->name('sales.invoice.detail');
            Route::get('/edit/{idPenjualan}', [SalesInvoiceController::class, 'editSalesInvoice'])->name('sales.invoice.edit');
            
            Route::post('/store', [SalesInvoiceController::class, 'storeSalesInvoice'])->name('sales.invoice.store');
            Route::put('/update/{idPenjualan}', [SalesInvoiceController::class, 'updateSalesInvoice'])->name('sales.invoice.update');
            Route::match(['delete', 'post'], '/delete/{idPenjualan}', [SalesInvoiceController::class, 'deleteSalesInvoice'])->name('sales.invoice.delete');
        });
        Route::prefix('/return')->group(function () {
            Route::get('/create', [SalesReturnController::class, 'createReturn'])->name('sales.return.create');
            Route::get('/edit/{idRetur}', [SalesReturnController::class, 'editReturn'])->name('sales.return.edit');
            Route::get('/detail/{idRetur}', [SalesReturnController::class, 'detailReturn'])->name('sales.return.detail');

            Route::post('/store', [SalesReturnController::class, 'storeReturn'])->name('sales.return.store');
            Route::put('/update/{idRetur}', [SalesReturnController::class, 'updateReturn'])->name('sales.return.update');
            Route::match(['post', 'delete'], '/delete/{idRetur}', [SalesReturnController::class, 'deleteReturn'])->name('sales.return.delete');

            Route::put('/update-allocate/{idRetur}', [SalesReturnController::class, 'updateAllocate'])->name('sales.return.update-allocate');
        });
        Route::prefix('/payment')->group(function () {
            Route::get('/create', [SalesPaymentController::class, 'createPayment'])->name('sales.payment.create');
            Route::get('/detail/{idPembayaran}', [SalesPaymentController::class, 'detailPayment'])->name('sales.payment.detail');
            Route::get('/edit/{idPembayaran}', [SalesPaymentController::class, 'editPayment'])->name('sales.payment.edit');
            
            Route::post('/store', [SalesPaymentController::class, 'storePayment'])->name('sales.payment.store');
            Route::put('/update/{idPembayaran}', [SalesPaymentController::class, 'updatePayment'])->name('sales.payment.update');
            Route::match(['delete', 'post'], '/delete/{idPembayaran}', [SalesPaymentController::class, 'deletePayment'])->name('sales.payment.delete');
        });
        Route::prefix('/down-payment')->group(function () {
            Route::get('/create', [SalesDownPaymentController::class, 'createPayment'])->name('sales.down-payment.create');
            Route::get('/detail/{idPembayaran}', [SalesDownPaymentController::class, 'detailPayment'])->name('sales.down-payment.detail');
            Route::get('/edit/{idPembayaran}', [SalesDownPaymentController::class, 'editPayment'])->name('sales.down-payment.edit');
            
            Route::post('/store', [SalesDownPaymentController::class, 'storePayment'])->name('sales.down-payment.store');
            Route::put('/update/{idPembayaran}', [SalesDownPaymentController::class, 'updatePayment'])->name('sales.down-payment.update');
            Route::match(['delete', 'post'], '/delete/{idPembayaran}', [SalesDownPaymentController::class, 'deletePayment'])->name('sales.down-payment.delete');
        });
        Route::prefix('/refund')->group(function () {
            Route::get('/create', [SalesRefundPaymentController::class, 'createRefund'])->name('sales.refund.create');
            Route::get('/edit/{idRefund}', [SalesRefundPaymentController::class, 'editRefund'])->name('sales.refund.edit');
            Route::get('/detail/{idRefund}', [SalesRefundPaymentController::class, 'detailRefund'])->name('sales.refund.detail');

            Route::post('/store', [SalesRefundPaymentController::class, 'storeRefund'])->name('sales.refund.store');
            Route::put('/update/{idRefund}', [SalesRefundPaymentController::class, 'updateRefund'])->name('sales.refund.update');
            Route::match(['post', 'delete'], '/delete/{idRefund}', [SalesRefundPaymentController::class, 'deleteRefund'])->name('sales.refund.delete');
        });
    });
    Route::prefix('ajax')->group(function () {
        Route::get('/akun', [AjaxController::class, 'akun'])->name('ajax.akun');
        Route::get('/varians', [AjaxController::class, 'getVarians'])->name('ajax.varians');
        Route::get('/attribut-value/{idAttribut?}', [AjaxController::class, 'attributValue'])->name('ajax.attribut-value');
        Route::get('/produk', [AjaxController::class, 'getProduk'])->name('ajax.produk');
        Route::get('/kontak/supplier', [AjaxController::class, 'getKontakSupplier'])->name('ajax.kontak-supplier');
        Route::get('/kontak/customer', [AjaxController::class, 'getKontakCustomer'])->name('ajax.kontak-customer');
        Route::get('/detail-produk', [AjaxController::class, 'getProdukDetail'])->name('ajax.produk-detail');
        Route::get('/pembelian', [AjaxController::class, 'getPembelian'])->name('ajax.pembelian');
        Route::get('/pembelian-detail', [AjaxController::class, 'getPembelianDetail'])->name('ajax.pembelian-detail');
        Route::get('/pembelian-pembayaran', [AjaxController::class, 'getPembelianPembayaran'])->name('ajax.pembelian-pembayaran');
        Route::get('/pembelian-pembayaran-detail', [AjaxController::class, 'getPembelianPembayaranDetail'])->name('ajax.pembelian-pembayaran-detail');
        Route::get('/penjualan', [AjaxController::class, 'getPenjualan'])->name('ajax.penjualan');
        Route::get('/penjualan-detail', [AjaxController::class, 'getPenjualanDetail'])->name('ajax.penjualan-detail');
        Route::get('/penjualan-pembayaran', [AjaxController::class, 'getPenjualanPembayaran'])->name('ajax.penjualan-pembayaran');
        Route::get('/penjualan-pembayaran-detail', [AjaxController::class, 'getPenjualanPembayaranDetail'])->name('ajax.penjualan-pembayaran-detail');
    });
});
