<?php

namespace App\Admin\Controllers;

use App\Models\PembelianRetur;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;

class PurchaseReturnController extends AdminController
{
    public function createReturnForm($model)
    {
        $form = new Form($model);
        $form->builder()->setTitle('Retur');
        $form->text('transaksi_no')->withoutIcon()->placeholder('[AUTO]');
        $form->select('id_kontak', 'Supplier')->setWidth(2)->ajax(route(admin_get_route('ajax.kontak')));
        $invoice = $form->select('id_pembelian', 'No. Transaksi')->setWidth(2);
        $url = route(admin_get_route('ajax.pembelian'));
        $urlDetailInvoice = route(admin_get_route('ajax.pembelian-detail'));
        $selectAjaxInvoice = <<<SCRIPT
            $("{$invoice->getElementClassSelector()}").select2({
                ajax: {
                    url: "$url",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                    return {
                        q: params.term,
                        page: params.page,
                        id_supplier: idSupplier
                    };
                    },
                    processResults: function (data, params) {
                    params.page = params.page || 1;

                    return {
                        results: $.map(data.data, function (d) {
                                d.id = d.id;
                                d.text = d.text;
                                return d;
                                }),
                        pagination: {
                        more: data.next_page_url
                        }
                    };
                    },
                    cache: true
                },
                escapeMarkup: function (markup) {
                    return markup;
                }
            });
        SCRIPT;
        $invoice->setScript($selectAjaxInvoice);
        $form->datetime('tanggal')->default(date('Y-m-d H:i:s'));
        $form->text('keterangan')->setWidth(4);
        $form->textarea('catatan');
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function editReturnForm($model, $idRetur)
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.return.edit'), ['idRetur' => $idRetur]));
        $data = $form->model()->with(['pembelian', 'pembelianDetail', 'kontak', 'pembelianReturDetail'])->findOrFail($idRetur);
        dump($data);
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Supplier')->setWidth(3);
            $form->html("<div style='padding-top: 7px'>#{$data->pembelian->transaksi_no}</div>", 'Invoice yang diretur')->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>#{$data->transaksi_no}</div>", 'No. Transaksi')->setWidth(2, 8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('pembelianDetail', 'Detail retur', function (NestedForm $form) {
                $data = $form->model();
                $checkDisable = $data?->jumlah_diinvoice == $data?->qty;
                if (!$checkDisable) {
                    $form->html("<input type='checkbox' name='pembelianDetail[{$data?->id_pembeliandetail}][check]' value='{$data?->id_pembeliandetail}'>", '<input id="checkAll" type="checkbox">');
                } else {
                    $form->html("<input type='checkbox' disabled>", '<input id="checkAll" type="checkbox">');
                }
                $form->html($data?->produkVarian?->varian, 'Produk')->required();
                $form->html(number($data?->jumlah_diinvoice ?: 0) . ' / ' . number($data?->qty), 'Qty')->setGroupClass('w-100px');
                if ($data?->jumlah_diinvoice >= $data?->qty and $data != null) {
                    $form->html('-', '');
                } else {
                    $form->text('qty', '')->customFormat(function ($val) use ($data) {
                        return number($val - $data?->jumlah_diinvoice);
                    })->attribute('type', 'number')->withoutIcon()->required()->setGroupClass('w-100px');
                }
                $form->currency('harga', 'Harga')->disable()->required()->symbol('Rp');
                $form->currency('diskon', 'Diskon')->disable()->symbol('%');
                $form->currency('total', 'Total')->disable()->symbol('Rp')->readonly();
            })->useTable()->disableCreate()->disableDelete();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->totalraw);
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%')->readonly()->value($data->diskon);
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->grandtotal);
            $form->textarea('catatan')->setWidth(4)->readonly()->value($data->catatan);
        });
        $form->column(12, function (Form $form) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->setWidth(4);
        });
        return $form;
    }
    public function detailReturnForm()
    {}

    public function createReturn(Content $content)
    {
        $scriptDereferred = <<<SCRIPT
            let idSupplier = null;
            $('select.id_kontak').change(function () {
                idSupplier = $('select.id_kontak').val();
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Retur Pembelian')
            ->description('Buat')
            ->body($this->createReturnForm(new PembelianRetur()));
    }
    public function editReturn(Content $content, $idRetur)
    {
        $style = <<<STYLE
            .input-group {
                width: 100% !important;   
            }
            .w-200px {
                width: 200px;
            }
            .w-100px {
                width: 100px;
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
                width: 20px;
            }
            [id^="has-many-"] table td:nth-child(2), [id^="has-many-"] table th:nth-child(2) {
                position: -webkit-sticky;
                position: sticky;
                left: 30px;
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
        $script = <<<SCRIPT
            const checkAll = document.querySelector("#checkAll");
            const products = document.querySelectorAll('[name^="pembelianDetail"]');
            checkAll.addEventListener("change", function () {
                products.forEach(product => {
                    product.checked = this.checked;
                });
            });

            for (const product of products) {
                product.addEventListener("click", updateDisplay);
            }
            function updateDisplay() {
                let checkedCount = 0;
                for (const product of products) {
                    if (product.checked) {
                    checkedCount++;
                    }
                }

                if (checkedCount === 0) {
                    checkAll.checked = false;
                    checkAll.indeterminate = false;
                } else if (checkedCount === products.length) {
                    checkAll.checked = true;
                    checkAll.indeterminate = false;
                } else {
                    checkAll.checked = false;
                    checkAll.indeterminate = true;
                }
            }
            $('select.form-control').each(function () {
                const select = this;
                const defaultValue = select.dataset.value.split(',');
                const defaultUrl = select.dataset.url;
                defaultValue.forEach(function (value) {
                    $.ajax({
                        type: 'GET',
                        url: defaultUrl + '?id=' + value
                    }).then(function (data) {
                        const option = new Option(data.text, data.id, true, true);
                        $(select).append(option).trigger('change');
                        $(select).trigger({
                            type: 'select2:select',
                            params: {
                                data: data
                            }
                        });
                    });
                })
            });
            $('#checkAll').trigger('click');
        SCRIPT;
        Admin::script($script);
        return $content
            ->title('Retur Pembelian')
            ->description('edit')
            ->body($this->editReturnForm(new PembelianRetur, $idRetur));
    }
    public function detailReturn(Content $content, $idRetur)
    {}

    public function storeReturn(Request $request)
    {}
    public function updateReturn(Request $request, $idRetur)
    {}
    public function deleteReturn(Request $request, $idRetur)
    {}
}
