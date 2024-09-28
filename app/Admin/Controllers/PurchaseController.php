<?php

namespace App\Admin\Controllers;

use App\Models\Dynamic;
use App\Models\Pembelian;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Row;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;

class PurchaseController extends AdminController
{
    public function createPurchaseOrderForm($model) {
        $form = new Form($model);
        $data = $form->model();
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Supplier')->ajax(route(admin_get_route('ajax.kontak')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2,8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(3, 7)->value(date('Y-m-d H:i:s'));
            $form->select('id_gudang', 'Gudang')->required()->width('100%')->setWidth(3, 7)->options(DB::table('toko_griyanaura.lv_gudang')->get()->pluck('nama', 'id_gudang'));
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('pembelianDetail', function (NestedForm $form) {
                $form->select('kode_produkvarian', 'Produk')->ajax(route(admin_get_route('ajax.produk')), 'kode_produkvarian')->setGroupClass('w-200px');
                $form->currency('qty', 'Qty')->symbol('QTY');
                $form->currency('harga', 'Harga')->symbol('Rp');
                $form->currency('diskon', 'Diskon')->symbol('%');
                $form->currency('Total', 'total')->symbol('Rp')->readonly();
            })->useTable();
        });
        $form->column(12, function (Form $form) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%');
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->setWidth(4);
        });
        return $form;
    }

    public function createPurchaseOrder(Content $content) {
        $style = <<<STYLE
        .input-group {
            width: 100% !important;   
        }
        .w-200px {
            width: 200px;
        }
        [id^="has-many-"] {
            position: relative;
            overflow: auto;
            white-space: nowrap;
        }
        [id^="has-many-"] table td:nth-child(1), [id^="has-many-"] table th:nth-child(1) {
            position: -webkit-sticky;
            position: sticky;
            left: 0px;
            background: white;
            z-index: 20;
            width: 200px;
        }
        [id^="has-many-"] table td:last-child, [id^="has-many-"] table th:last-child {
            position: -webkit-sticky;
            position: sticky;
            right: 0px;
            background: white;
            z-index: 10;
        }
        [id^="has-many-"] .form-group:has(.add) {
            width: 100%;
            position: -webkit-sticky;
            position: sticky;
            left: 0px;
            background: white;
            z-index: 10;
        }
        [class*='col-md-'] {
            margin-bottom: 2rem;
        }
        STYLE;
        Admin::style($style);
        $urlGetDetailProduk = route(admin_get_route('ajax.produk-detail'));
        $scriptDereferred = <<<SCRIPT
        $("#has-many-pembelianDetail").on('click', '.add', function () {
            $(".pembelianDetail.kode_produkvarian").on('select2:select', function (e) {
                const kode = e.params.data.id;
                const idGudang = $('select[name="id_gudang"]').val();
                $.ajax({
                    url: '$urlGetDetailProduk',
                    type: 'GET',
                    data: {
                        kode_produkvarian: kode,
                        id_gudang: idGudang
                    },
                    success: function(data) {
                        // Jika permintaan berhasil
                        console.log('Data berhasil diterima:', data);
                        $(e.target).closest('tr').find('[name^="harga"]').val(data.hargabeli);
                        console.info(e.target);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        // Menangani kesalahan
                        switch (jqXHR.status) {
                            case 404:
                                console.log('Error 404: Tidak ditemukan.');
                                break;
                            case 500:
                                console.log('Error 500: Kesalahan server.');
                                break;
                            default:
                                console.log('Kesalahan: ' + textStatus);
                                break;
                        }
                    }
                });
            });
        });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        $pembelian = new Pembelian();
        return $content
            ->title('Order Pembelian')
            ->description('Buat')
            ->body($this->createPurchaseOrderForm($pembelian));
    }
}
