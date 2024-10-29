<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\Delete;
use App\Admin\Actions\Grid\Edit;
use App\Admin\Actions\Grid\Show;
use App\Exceptions\PurchaseOrderException;
use App\Models\Dynamic;
use App\Models\Pembelian;
use App\Services\Core\Purchase\PurchaseOrderService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\Footer;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Row;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Displayers\DropdownActions;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row as LayoutRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends AdminController
{
    protected $purchaseOrderService;
    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }
    public function listPurchaseOrderGrid() {
        $grid = new Grid(new Pembelian);
        $grid->model()->where('jenis', 'order')->with(['kontak', 'gudang', 'pembelianOrder']);
        if (!isset($_GET['_sort']['column']) and empty($_GET['sort']['column'])) {
            $grid->model()->orderByRaw('id_pembelian desc');
        }
        $grid->column('transaksi_no', 'No. Transaksi')->link(function () {
            return url()->route(admin_get_route('purchase.order.detail'), ['idPembelian' => $this->id_pembelian]);
        })->sortable();
        $grid->column('kontak.nama', 'Supplier')->sortable();
        $grid->column('tanggal', 'Tanggal')->display(function ($val) {
            return \date('d F Y', \strtotime($val));
        })->sortable();
        $grid->column('gudang.nama', 'Gudang');
        // $grid->column('tanggaltempo', 'Tanggal tempo')->display(function ($val) {
        //     if ($val) 
        //         return \date('d F Y', \strtotime($val));
        //     else 
        //         return null;
        // });
        $grid->column('catatan', 'Catatan');
        $grid->column('grandtotal', 'Grand total')->display(function ($val) {
            return 'Rp' . number_format($val, 0, ',', '.');
        });
        $grid->actions(function (DropdownActions $actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
            $actions->add(new Show);
            $actions->add(new Edit);
            // dump($this);
            $actions->add(new Delete(route(admin_get_route('purchase.order.delete'), $this->row->id_pembelian)));
        });
        return $grid;
    }
    public function listPurchaseOrder(Content $content) {
        return $content
            ->title('Pembelian Order')
            ->description('Daftar')
            ->row(function (LayoutRow $row) {
                $row->column(12, function (Column $column) {
                    $column->row($this->listPurchaseOrderGrid());
                });
            });
    }

    
    public function createPurchaseOrderForm($model) {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.order.store')));
        $data = $form->model();
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak-supplier')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2,8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
            $form->select('id_gudang', 'Gudang')->required()->width('100%')->setWidth(2, 8)->options(DB::table('toko_griyanaura.lv_gudang')->get()->pluck('nama', 'id_gudang'))->default(1);
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('pembelianDetail', function (NestedForm $form) {
                $form->select('kode_produkvarian', 'Produk')->required()->ajax(route(admin_get_route('ajax.produk')), 'kode_produkvarian')->setGroupClass('w-200px');
                $form->currency('qty', 'Qty')->required()->symbol('QTY');
                $form->currency('harga', 'Harga')->required()->symbol('Rp');
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
    public function detailPurchaseOrderForm($model, $idPembelian) {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.order.to-invoice'), ['idPembelian' => $idPembelian]));
        $data = $form->model()->with(['kontak','pembelianDetail' => function ($rel) {
            $rel->leftJoin(DB::raw("(select id_pembeliandetailparent, sum(qty) as jumlah_diinvoice from toko_griyanaura.tr_pembeliandetail where id_pembeliandetailparent is not null group by id_pembeliandetailparent) as x"), 'x.id_pembeliandetailparent', 'toko_griyanaura.tr_pembeliandetail.id_pembeliandetail');
            $rel->with('produkVarian');
        } ])->where('id_pembelian', $idPembelian)->join(DB::raw("(select id_gudang, nama as nama_gudang from toko_griyanaura.lv_gudang) as gdg"), 'gdg.id_gudang', 'toko_griyanaura.tr_pembelian.id_gudang')->first();
        $form->tools(function (Tools $tools) use ($idPembelian, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.order.delete'), ['idPembelian' => $idPembelian]), listPath: route(admin_get_route('purchase.order.create'))));
            $tools->append($tools->renderEdit(route(admin_get_route('purchase.order.edit'), ['idPembelian' => $idPembelian])));
            $tools->append($tools->renderEdit(route(admin_get_route('purchase.order.to-invoice'), ['idPembelian' => $idPembelian]), text: 'Buat ke invoice', icon: 'fa-file-text'));
            $tools->append($tools->renderList(route(admin_get_route('purchase.order.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Supplier')->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px;'>#{$data->transaksi_no}</div>", 'No. Transaksi')->setWidth(2, 8);
            $form->html("<div style='padding-top: 7px;'>{$data->tanggal}</div>", 'Tanggal')->setWidth(2, 8);
            $form->html("<div style='padding-top: 7px;'>{$data->nama_gudang}</div>", 'Gudang')->setWidth(2, 8);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('pembelianDetail', function (NestedForm $form) {
                $data = $form->model();
                $form->html($data?->produkVarian?->varian, 'Produk')->required();
                $form->text('qty', 'Qty')->customFormat(function ($val) {
                    return number($val);
                })->disable()->attribute('type', 'number')->withoutIcon()->required()->setGroupClass('w-100px');
                $form->currency('harga', 'Harga')->disable()->required()->symbol('Rp');
                $form->currency('diskon', 'Diskon')->disable()->symbol('%');
                $form->currency('total', 'Total')->disable()->symbol('Rp')->readonly();
            })->value($data->pembelianDetail->toArray())->useTable()->disableCreate()->disableDelete();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->totalraw);
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%')->readonly()->value($data->diskon);
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->grandtotal);
            $form->textarea('catatan')->setWidth(4)->readonly()->value($data->catatan);
        });
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableSubmit();
        $form->disableReset();

        return $form;
    }
    public function toInvoicePurchaseOrderForm($model, $idPembelian) {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.order.to-invoice.store'), ['idPembelian' => $idPembelian]));
        $data = $form->model()->with(['kontak','pembelianDetail' => function ($rel) {
            $rel->leftJoin(DB::raw("(select id_pembeliandetailparent, sum(qty) as jumlah_diinvoice from toko_griyanaura.tr_pembeliandetail where id_pembeliandetailparent is not null group by id_pembeliandetailparent) as x"), 'x.id_pembeliandetailparent', 'toko_griyanaura.tr_pembeliandetail.id_pembeliandetail');
            $rel->with('produkVarian');
        } ])->where('id_pembelian', $idPembelian)->join(DB::raw("(select id_gudang, nama as nama_gudang from toko_griyanaura.lv_gudang) as gdg"), 'gdg.id_gudang', 'toko_griyanaura.tr_pembelian.id_gudang')->first();
        $form->tools(function (Tools $tools) use ($idPembelian, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.order.delete'), ['idPembelian' => $idPembelian]), listPath: route(admin_get_route('purchase.order.create'))));
            $tools->append($tools->renderView(route(admin_get_route('purchase.order.detail'), ['idPembelian' => $idPembelian])));
            $tools->append($tools->renderList(route(admin_get_route('purchase.order.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Supplier')->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px;'>#{$data->transaksi_no}</div>", 'No. Transaksi')->setWidth(2, 8);
            $form->datetime("tanggal", 'Tanggal')->required()->setWidth(2, 8)->width('100%')->value(date('Y-m-d H:i:s'));
            $form->datetime("tanggaltempo", 'Tanggal Tempo')->required()->setWidth(2, 8)->width('100%')->value(date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . ' +1 day')));
            $form->html("<div style='padding-top: 7px;'>{$data->nama_gudang}</div>", 'Gudang')->setWidth(2, 8);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('pembelianDetail', function (NestedForm $form) {
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
            })->value($data->pembelianDetail->toArray())->useTable()->disableCreate()->disableDelete();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->totalraw);
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%')->readonly()->value($data->diskon);
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->grandtotal);
            $form->textarea('catatan')->setWidth(4)->readonly()->value($data->catatan);
        });
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        // $form->disableSubmit();
        $form->disableReset();
        $form->button();

        return $form;
    }
    public function editPurchaseOrderForm($model, $idPembelian) {
        $form = new Form($model);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('purchase.order.update'), ['idPembelian' => $idPembelian]));
        $data = $form->model()->with('pembelianDetail')->where('id_pembelian', $idPembelian)->first();
        $form->tools(function (Tools $tools) use ($idPembelian, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.order.delete'), ['idPembelian' => $idPembelian]), listPath: route(admin_get_route('purchase.order.create'))));
            $tools->append($tools->renderView(route(admin_get_route('purchase.order.detail'), ['idPembelian' => $idPembelian])));
            $tools->append($tools->renderList(route(admin_get_route('purchase.order.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak-supplier')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak-supplier')),
                'select2' => null
            ])->setWidth(3)->value($data->id_kontak);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->text('transaksi_no', 'No. Transaksi')->disable()->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2,8)->value($data->transaksi_no);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(3, 7)->value($data->tanggal);
            $form->select('id_gudang', 'Gudang')->required()->width('100%')->setWidth(3, 7)->options(DB::table('toko_griyanaura.lv_gudang')->get()->pluck('nama', 'id_gudang'))->value($data->id_gudang);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('pembelianDetail', function (NestedForm $form) {
                $form->select('kode_produkvarian', 'Produk')->required()->ajax(route(admin_get_route('ajax.produk')), 'kode_produkvarian')->attribute([
                    'data-url' => route(admin_get_route('ajax.produk')),
                    'select2' => null
                ])->setGroupClass('w-200px');
                $form->currency('qty', 'Qty')->required()->symbol('QTY');
                $form->currency('harga', 'Harga')->required()->symbol('Rp');
                $form->currency('diskon', 'Diskon')->symbol('%');
                $form->currency('total', 'Total')->symbol('Rp')->readonly();
            })->value($data->pembelianDetail->toArray())->useTable();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->totalraw);
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%')->value($data->diskon);
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->grandtotal);
            $form->textarea('catatan')->setWidth(4)->value($data->catatan);
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
            function hitungProdukTotal(e) {
                const row = $(e.target).closest('tr');
                const harga = parseInt(row.find('.pembelianDetail[name*="harga"]').first().inputmask('unmaskedvalue')) || 0;
                const qty = parseFloat(row.find('.pembelianDetail[name*="qty"]').first().inputmask('unmaskedvalue')) || 0;
                const diskon = parseFloat(row.find('.pembelianDetail[name*="diskon"]').first().inputmask('unmaskedvalue')) || 0;
                const total = parseInt(harga*qty*(1-diskon/100));
                row.find('.pembelianDetail[name*="Total"]').first().val(total).change();
            }
            function hitungTotal() {
                let total = 0;
                $('.pembelianDetail[name*="Total"]:visible').each(function (e, item) {
                    total += parseInt($(item).inputmask('unmaskedvalue')) || 0;
                });
                $('[name="totalraw"]').val(total).change();
            }
            function hitungGrandTotal() {
                const diskon = parseFloat($('[name="diskon"]').inputmask('unmaskedvalue')) || 0;
                const total = parseInt($('[name="totalraw"]').inputmask('unmaskedvalue')) || 0;
                $('[name="total"]').val(total*(1-diskon/100));
            }
            $("#has-many-pembelianDetail").on('click', '.remove', function () {
                hitungTotal();
            });
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
                            if (data?.produk_persediaan) {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_persediaan[0].produk_varian_harga.hargabeli);
                            } else {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_varian_harga[0].hargabeli);
                            }
                            $(e.target).closest('tr').find('[name*="qty"]').val(1);
                            hitungProdukTotal(e);
                            hitungTotal(e);
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
                $('.pembelianDetail[name*="harga"],.pembelianDetail[name*="qty"],.pembelianDetail[name*="diskon"]').on('change', function (e) {
                    hitungProdukTotal(e);
                });
                $('.pembelianDetail[name*="Total"]').on('change', function (e) {
                    hitungTotal();
                });
            });
            $('[name="totalraw"],[name="diskon"]').on('change', function () {
                hitungGrandTotal();
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        $pembelian = new Pembelian();
        return $content
            ->title('Order Pembelian')
            ->description('Buat')
            ->body($this->createPurchaseOrderForm($pembelian));
    }
    public function detailPurchaseOrder(Content $content, $idPembelian) {
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
                left: 0;
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
        SCRIPT;
        Admin::script($script);
        $pembelian = new Pembelian();
        if (!$pembelian->where('id_pembelian', $idPembelian)->where('jenis', 'order')->first()) {
            abort(404);
        }
        return $content
            ->title('Order Pembelian')
            ->description('Detail')
            ->body($this->detailPurchaseOrderForm($pembelian, $idPembelian));
    }
    public function toInvoicePurchaseOrder(Content $content, $idPembelian) {
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
        $pembelian = new Pembelian();
        if (!$pembelian->where('id_pembelian', $idPembelian)->where('jenis', 'order')->first()) {
            abort(404);
        }
        return $content
            ->title('Order Pembelian')
            ->description('Ke invoice')
            ->body($this->toInvoicePurchaseOrderForm($pembelian, $idPembelian));
    }
    public function editPurchaseOrder(Content $content, $idPembelian) {
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
            $(".pembelianDetail.kode_produkvarian").on('select2:select', function (e) {
                const kode = e.params.data.kode_produkvarian;
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
                        if (data?.produk_persediaan) {
                            $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_persediaan[0].produk_varian_harga.hargabeli);
                        } else {
                            $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_varian_harga[0].hargabeli);
                        }
                        if (!$(e.target).closest('tr').find('[name*="qty"]').val()) {
                            $(e.target).closest('tr').find('[name*="qty"]').val(1);
                        }
                        hitungProdukTotal(e);
                        hitungTotal(e);
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
            $('[select2]').each(function () {
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
            function hitungProdukTotal(e) {
                const row = $(e.target).closest('tr');
                const harga = parseInt(row.find('.pembelianDetail[name*="harga"]').first().inputmask('unmaskedvalue')) || 0;
                const qty = parseFloat(row.find('.pembelianDetail[name*="qty"]').first().inputmask('unmaskedvalue')) || 0;
                const diskon = parseFloat(row.find('.pembelianDetail[name*="diskon"]').first().inputmask('unmaskedvalue')) || 0;
                const total = parseInt(harga*qty*(1-diskon/100));
                row.find('.pembelianDetail[name*="total"]').first().val(total).change();
            }
            function hitungTotal() {
                let total = 0;
                $('.pembelianDetail[name*="total"]:visible').each(function (e, item) {
                    total += parseInt($(item).inputmask('unmaskedvalue')) || 0;
                });
                $('[name="totalraw"]').val(total).change();
            }
            function hitungGrandTotal() {
                const diskon = parseFloat($('[name="diskon"]').inputmask('unmaskedvalue')) || 0;
                const total = parseInt($('[name="totalraw"]').inputmask('unmaskedvalue')) || 0;
                $('[name="total"]').val(total*(1-diskon/100));
            }
            $("#has-many-pembelianDetail").on('click', '.remove', function () {
                console.info('hit');
                hitungTotal();
            });
            $('.pembelianDetail[name*="harga"],.pembelianDetail[name*="qty"],.pembelianDetail[name*="diskon"]').on('change', function (e) {
                hitungProdukTotal(e);
            });
            $('.pembelianDetail[name*="total"]').on('change', function (e) {
                hitungTotal();
            });
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
                            if (data?.produk_persediaan) {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_persediaan[0].produk_varian_harga.hargabeli);
                            } else {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_varian_harga[0].hargabeli);
                            }
                            if (!$(e.target).closest('tr').find('[name*="qty"]').val()) {
                                $(e.target).closest('tr').find('[name*="qty"]').val(1);
                            }
                            hitungProdukTotal(e);
                            hitungTotal(e);
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
                $('.pembelianDetail[name*="harga"],.pembelianDetail[name*="qty"],.pembelianDetail[name*="diskon"]').on('change', function (e) {
                    hitungProdukTotal(e);
                });
                $('.pembelianDetail[name*="total"]').on('change', function (e) {
                    hitungTotal();
                });
            });
            $('[name="totalraw"],[name="diskon"]').on('change', function () {
                hitungGrandTotal();
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        $pembelian = new Pembelian();
        if (!$pembelian->where('id_pembelian', $idPembelian)->where('jenis', 'order')->first()) {
            abort(404);
        }
        if ($pembelian->has('pembelianInvoice')->where('id_pembelian', $idPembelian)->first()) {
            admin_toastr('Pembelian sudah di-invoice, perubahan tidak diizinkan', 'warning');
            return redirect()->route(admin_get_route('purchase.order.detail'), ['idPembelian' => $idPembelian]);
        }
        return $content
            ->title('Order Pembelian')
            ->description('Ubah')
            ->body($this->editPurchaseOrderForm($pembelian, $idPembelian));
    }

    public function storePurchaseOrder(Request $request) {
        try {
            $result = $this->purchaseOrderService->storePurchaseOrder($request->all());
            admin_toastr('Sukses buat transaksi pembelian');
            return redirect()->route(admin_get_route('purchase.order.detail'), ['idPembelian' => $result->id_pembelian]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function updatePurchaseOrder(Request $request, $idPembelian) {
        try {
            $this->purchaseOrderService->updatePurchaseOrder($idPembelian, $request->all());
            admin_toastr('Sukses memperbarui transaksi pembelian');
            return redirect()->route(admin_get_route('purchase.order.detail'), ['idPembelian' => $idPembelian]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function storeToInvoicePurchaseOrder(Request $request, $idPembelian) {
        try {
            $result = $this->purchaseOrderService->storeToInvoicePurchaseOrder($idPembelian, $request->all());
            admin_toastr('Sukses buat invoice pembelian');
            return redirect()->route(admin_get_route('purchase.invoice.detail'), ['idPembelian' => $result->id_pembelian]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function deletePurchaseOrder(Request $request, $idPembelian) {
        try {
            $this->purchaseOrderService->deletePurchaseOrder($idPembelian);
            admin_toastr('Sukses hapus order pembelian');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus order pembelian'
            ];
        } catch (PurchaseOrderException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return [
                'status' => false,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
