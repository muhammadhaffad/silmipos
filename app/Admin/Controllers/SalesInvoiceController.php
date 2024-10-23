<?php

namespace App\Admin\Controllers;

use App\Exceptions\SalesInvoiceException;
use App\Models\Penjualan;
use App\Services\Core\Sales\SalesInvoiceService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceController extends AdminController
{
    protected $salesInvoiceService;
    public function __construct(SalesInvoiceService $salesInvoiceService)
    {
        $this->salesInvoiceService = $salesInvoiceService;
    }
    public function createSalesInvoiceForm($model) 
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('sales.invoice.store')));
        $data = $form->model();
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Customer')->required()->ajax(route(admin_get_route('ajax.kontak-customer')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2,8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
            $form->datetime('tanggaltempo', 'Tanggal Tempo')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . '+1 days')));
            $form->select('id_gudang', 'Gudang')->required()->width('100%')->setWidth(2, 8)->options(DB::table('toko_griyanaura.lv_gudang')->get()->pluck('nama', 'id_gudang'))->default(1);
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('penjualanDetail', function (NestedForm $form) {
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
    public function editSalesInvoiceForm($model, $idPenjualan) 
    {
        $form = new Form($model);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('sales.invoice.update'), ['idPenjualan' => $idPenjualan]));
        $data = $form->model()->with(['penjualanDetail' => function ($q) {
            $q->orderBy('id_penjualandetail');
            $q->with('produkVarian');
        }])->where('id_penjualan', $idPenjualan)->first();
        $form->tools(function (Tools $tools) use ($idPenjualan, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('sales.invoice.delete'), ['idPenjualan' => $idPenjualan]), listPath: route(admin_get_route('sales.invoice.create'))));
            $tools->append($tools->renderView(route(admin_get_route('sales.invoice.detail'), ['idPenjualan' => $idPenjualan])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Customer')->required()->ajax(route(admin_get_route('ajax.kontak-customer')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak-customer')),
                'select2' => null
            ])->setWidth(3)->value($data->id_kontak);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->text('transaksi_no', 'No. Transaksi')->disable()->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2,8)->value($data->transaksi_no);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value($data->tanggal);
            $form->datetime('tanggaltempo', 'Tanggal Tempo')->required()->width('100%')->setWidth(2, 8)->value($data->tanggaltempo);
            $form->select('id_gudang', 'Gudang')->required()->width('100%')->setWidth(2, 8)->options(DB::table('toko_griyanaura.lv_gudang')->get()->pluck('nama', 'id_gudang'))->value($data->id_gudang);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('penjualanDetail', function (NestedForm $form) {
                if ($form->model()) {
                    $text = <<<HTML
                        <div class="form-group w-200px">
                            <label class="col-sm-0 hidden control-label">Produk</label>
                            <div class="col-sm-12" style="text-wrap: pretty">
                                {$form->model()['produkVarian']['varian']}
                            </div>
                        </div>
                    HTML;
                    $form->html($text)->plain();
                } else {
                    $form->select('kode_produkvarian', 'Produk')->required()->ajax(route(admin_get_route('ajax.produk')), 'kode_produkvarian')->attribute([
                        'data-url' => route(admin_get_route('ajax.produk')),
                        'select2' => null
                    ])->setGroupClass('w-200px');
                }
                $form->currency('qty', 'Qty')->required()->symbol('QTY');
                $form->currency('harga', 'Harga')->required()->symbol('Rp');
                $form->currency('diskon', 'Diskon')->symbol('%');
                $form->currency('total', 'Total')->symbol('Rp')->readonly();
            })->value($data->penjualanDetail->toArray())->useTable();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->totalraw);
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%')->value($data->diskon);
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->grandtotal);
            $form->textarea('catatan')->setWidth(4)->value($data->catatan);
        });
        return $form;
    }
    public function detailSalesInvoiceForm($model, $idPenjualan) 
    {
        $form = new Form($model);
        $form->setAction('');
        $data = $form->model()->with(['kontak','penjualanDetail' => function ($rel) {
            $rel->orderBy('id_penjualandetail');
            $rel->leftJoin(DB::raw("(select id_penjualandetailparent, sum(qty) as jumlah_diinvoice from toko_griyanaura.tr_penjualandetail where id_penjualandetailparent is not null group by id_penjualandetailparent) as x"), 'x.id_penjualandetailparent', 'toko_griyanaura.tr_penjualandetail.id_penjualandetail');
            $rel->with('produkVarian');
        } ])->where('id_penjualan', $idPenjualan)->join(DB::raw("(select id_gudang, nama as nama_gudang from toko_griyanaura.lv_gudang) as gdg"), 'gdg.id_gudang', 'toko_griyanaura.tr_penjualan.id_gudang')->first();
        $form->tools(function (Tools $tools) use ($idPenjualan, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('sales.invoice.delete'), ['idPenjualan' => $idPenjualan]), listPath: route(admin_get_route('sales.invoice.create'))));
            $tools->append($tools->renderEdit(route(admin_get_route('sales.invoice.edit'), ['idPenjualan' => $idPenjualan])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Customer')->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $tanggalTempo = date('d F Y', strtotime($data->tanggaltempo));
            $tanggal = date('d F Y', strtotime($data->tanggal));
            $form->html("<div style='padding-top: 7px;'>#{$data->transaksi_no}</div>", 'No. Transaksi')->setWidth(2, 8);
            $form->html("<div style='padding-top: 7px;'>{$tanggal}</div>", 'Tanggal')->setWidth(2, 8);
            $form->html("<div style='padding-top: 7px;'>{$tanggalTempo}</div>", 'Tanggal Tempo')->setWidth(2, 8);
            $form->html("<div style='padding-top: 7px;'>{$data->nama_gudang}</div>", 'Gudang')->setWidth(2, 8);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('penjualanDetail', function (NestedForm $form) {
                $data = $form->model();
                $form->html($data?->produkVarian?->varian, 'Produk')->required();
                $form->text('qty', 'Qty')->customFormat(function ($val) {
                    return number($val);
                })->disable()->attribute('type', 'number')->withoutIcon()->required()->setGroupClass('w-100px');
                $form->currency('harga', 'Harga')->disable()->required()->symbol('Rp');
                $form->currency('diskon', 'Diskon')->disable()->symbol('%');
                $form->currency('total', 'Total')->disable()->symbol('Rp')->readonly();
            })->value($data->penjualanDetail->toArray())->useTable()->disableCreate()->disableDelete();
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

    public function createSalesInvoice(Content $content) 
    {
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
                const harga = parseInt(row.find('.penjualanDetail[name*="harga"]').first().inputmask('unmaskedvalue')) || 0;
                const qty = parseFloat(row.find('.penjualanDetail[name*="qty"]').first().inputmask('unmaskedvalue')) || 0;
                const diskon = parseFloat(row.find('.penjualanDetail[name*="diskon"]').first().inputmask('unmaskedvalue')) || 0;
                const total = parseInt(harga*qty*(1-diskon/100));
                row.find('.penjualanDetail[name*="Total"]').first().val(total).change();
            }
            function hitungTotal() {
                let total = 0;
                $('.penjualanDetail[name*="Total"]:visible').each(function (e, item) {
                    total += parseInt($(item).inputmask('unmaskedvalue')) || 0;
                });
                $('[name="totalraw"]').val(total).change();
            }
            function hitungGrandTotal() {
                const diskon = parseFloat($('[name="diskon"]').inputmask('unmaskedvalue')) || 0;
                const total = parseInt($('[name="totalraw"]').inputmask('unmaskedvalue')) || 0;
                $('[name="total"]').val(total*(1-diskon/100));
            }
            $("#has-many-penjualanDetail").on('click', '.remove', function () {
                hitungTotal();
            });
            $("#has-many-penjualanDetail").on('click', '.add', function () {
                $(".penjualanDetail.kode_produkvarian").on('select2:select', function (e) {
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
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_persediaan[0].produk_varian_harga.hargajual);
                            } else {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_varian_harga[0].hargajual);
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
                $('.penjualanDetail[name*="harga"],.penjualanDetail[name*="qty"],.penjualanDetail[name*="diskon"]').on('change', function (e) {
                    hitungProdukTotal(e);
                });
                $('.penjualanDetail[name*="Total"]').on('change', function (e) {
                    hitungTotal();
                });
            });
            $('[name="totalraw"],[name="diskon"]').on('change', function () {
                hitungGrandTotal();
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        $penjualan = new Penjualan();
        return $content
            ->title('Invoice Penjualan')
            ->description('Buat')
            ->body($this->createSalesInvoiceForm($penjualan));
    }
    public function editSalesInvoice(Content $content, $idPenjualan) 
    {
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
            $(".penjualanDetail.kode_produkvarian").on('select2:select', function (e) {
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
                            $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_persediaan[0].produk_varian_harga.hargajual);
                        } else {
                            $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_varian_harga[0].hargajual);
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
                const harga = parseInt(row.find('.penjualanDetail[name*="harga"]').first().inputmask('unmaskedvalue')) || 0;
                const qty = parseFloat(row.find('.penjualanDetail[name*="qty"]').first().inputmask('unmaskedvalue')) || 0;
                const diskon = parseFloat(row.find('.penjualanDetail[name*="diskon"]').first().inputmask('unmaskedvalue')) || 0;
                const total = parseInt(harga*qty*(1-diskon/100));
                row.find('.penjualanDetail[name*="total"]').first().val(total).change();
            }
            function hitungTotal() {
                let total = 0;
                $('.penjualanDetail[name*="total"]:visible').each(function (e, item) {
                    total += parseInt($(item).inputmask('unmaskedvalue')) || 0;
                });
                $('[name="totalraw"]').val(total).change();
            }
            function hitungGrandTotal() {
                const diskon = parseFloat($('[name="diskon"]').inputmask('unmaskedvalue')) || 0;
                const total = parseInt($('[name="totalraw"]').inputmask('unmaskedvalue')) || 0;
                $('[name="total"]').val(total*(1-diskon/100));
            }
            $("#has-many-penjualanDetail").on('click', '.remove', function () {
                console.info('hit');
                hitungTotal();
            });
            $('.penjualanDetail[name*="harga"],.penjualanDetail[name*="qty"],.penjualanDetail[name*="diskon"]').on('change', function (e) {
                hitungProdukTotal(e);
            });
            $('.penjualanDetail[name*="total"]').on('change', function (e) {
                hitungTotal();
            });
            $("#has-many-penjualanDetail").on('click', '.add', function () {
                $(".penjualanDetail.kode_produkvarian").on('select2:select', function (e) {
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
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_persediaan[0].produk_varian_harga.hargajual);
                            } else {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.produk_varian_harga[0].hargajual);
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
                $('.penjualanDetail[name*="harga"],.penjualanDetail[name*="qty"],.penjualanDetail[name*="diskon"]').on('change', function (e) {
                    hitungProdukTotal(e);
                });
                $('.penjualanDetail[name*="total"]').on('change', function (e) {
                    hitungTotal();
                });
            });
            $('[name="totalraw"],[name="diskon"]').on('change', function () {
                hitungGrandTotal();
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        $penjualan = new Penjualan();
        if (!$penjualan->where('id_penjualan', $idPenjualan)->where('jenis', 'invoice')->first()) {
            abort(404);
        }
        return $content
            ->title('Invoice Penjualan')
            ->description('Ubah')
            ->body($this->editSalesInvoiceForm($penjualan, $idPenjualan));
    }
    public function detailSalesInvoice(Content $content, $idPenjualan) {
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
        $penjualan = new Penjualan();
        if (!$penjualan->where('id_penjualan', $idPenjualan)->where('jenis', 'invoice')->first()) {
            abort(404);
        }
        return $content
            ->title('Invoice Penjualan')
            ->description('Detail')
            ->body($this->detailSalesInvoiceForm($penjualan, $idPenjualan));
    }

    public function storeSalesInvoice(Request $request) {
        try {
            $result = $this->salesInvoiceService->storeSalesInvoice($request->all());
            admin_toastr('Sukses buat transaksi penjualan');
            return redirect()->route(admin_get_route('sales.invoice.detail'), ['idPenjualan' => $result->id_penjualan]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (SalesInvoiceException $e) {
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
    public function updateSalesInvoice(Request $request, $idPenjualan) {
        try {
            $result = $this->salesInvoiceService->updateSalesInvoice($idPenjualan, $request->all());
            admin_toastr('Sukses ubah transaksi penjualan');
            // return redirect()->route(admin_get_route('sales.invoice.detail'), ['idPenjualan' => $result->id_penjualan]);
            return redirect()->route(admin_get_route('sales.invoice.edit'), ['idPenjualan' => $result->id_penjualan]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (SalesInvoiceException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            return $e;
        }
    }
    public function deleteSalesInvoice(Request $request, $idPenjualan) {
        try {
            $this->salesInvoiceService->deleteSalesInvoice($idPenjualan);
            admin_toastr('Sukses hapus invoice penjualan');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus order penjualan'
            ];
        } catch (SalesInvoiceException $e) {
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
