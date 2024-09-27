<?php

namespace App\Admin\Controllers;

use App\Models\Pembelian;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\Row;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;

class PurchaseController extends AdminController
{
    public function createPurchaseOrderForm($model) {
        $form = new Form($model);
        $data = $form->model();
        $form->column(6, function (Form $form) {
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(5, 4)->value(date('Y-m-d H:i:s'));
            $form->datetime('tanggaltempo', 'Tanggal tempo')->required()->width('100%')->setWidth(5, 4)->setLabelClass(['text-nowrap']);
            $form->datetime('tanggalkirim', 'Tanggal kirim')->width('100%')->setWidth(5, 4)->setLabelClass(['text-nowrap']);
        });
        $form->column(6, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(5,3);
            $form->text('deskripsi', 'Deskripsi')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(5,3);
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('pembelianDetail', function () {})->useTable();
            $form->textarea('catatan')->setWidth(4);
        });
        return $form;
    }

    public function createPurchaseOrder(Content $content) {
        $style = <<<STYLE
        .input-group {
            width: 100% !important;   
        }
        STYLE;
        Admin::style($style);
        $pembelian = new Pembelian();
        return $content
            ->title('Order Pembelian')
            ->description('Buat')
            ->body($this->createPurchaseOrderForm($pembelian));
    }
}
