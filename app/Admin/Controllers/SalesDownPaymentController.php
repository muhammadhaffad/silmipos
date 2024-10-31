<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\Delete;
use App\Admin\Actions\Grid\Edit;
use App\Admin\Actions\Grid\Show;
use App\Exceptions\SalesPaymentException;
use App\Models\PenjualanPembayaran;
use App\Services\Core\Sales\SalesDownPaymentService;
use App\Services\Core\Sales\SalesPaymentService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Displayers\DropdownActions;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDownPaymentController extends AdminController
{
    protected $salesDownPaymentService;
    public function __construct(SalesDownPaymentService $salesDownPaymentService)
    {
        $this->salesDownPaymentService = $salesDownPaymentService;
    }

    public function listPaymentGrid() {
        $grid = new Grid(new PenjualanPembayaran);
        $grid->model()->where('jenisbayar', 'DP')->with(['kontak']);
        if (!isset($_GET['_sort']['column']) and empty($_GET['sort']['column'])) {
            $grid->model()->orderByRaw('id_penjualanpembayaran desc');
        }
        $grid->column('transaksi_no', 'No. Transaksi')->link(function () {
            return url()->route(admin_get_route('sales.down-payment.detail'), ['idPembayaran' => $this->id_penjualanpembayaran]);
        })->sortable();
        $grid->column('tanggal', 'Tanggal')->display(function ($val) {
            return \date('d F Y', \strtotime($val));
        })->sortable();
        $grid->column('kontak.nama', 'Customer')->sortable();
        $grid->column('catatan', 'Catatan');
        $grid->column('nominal', 'Total')->display(function ($val) {
            return 'Rp' . number_format($val, 0, ',', '.');
        });
        $grid->actions(function (DropdownActions $actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
            $actions->add(new Show);
            $actions->add(new Edit);
            // dump($this);
            $actions->add(new Delete(route(admin_get_route('sales.down-payment.delete'), $this->row->id_penjualanpembayaran)));
        });
        return $grid;
    }
    public function listPayment(Content $content) {
        return $content
            ->title('Penjualan Pembayaran DP')
            ->description('Daftar')
            ->row(function (Row $row) {
                $row->column(12, function (Column $column) {
                    $column->row($this->listPaymentGrid());
                });
            });
    }

    public function createPaymentForm($model)
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('sales.down-payment.store')));
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Customer')->required()->ajax(route(admin_get_route('ajax.kontak-customer')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2, 8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
        });
        
        $form->column(12, function (Form $form) {
            $form->currency('totaldp', 'Nominal DP')->symbol('Rp');
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('penjualanAlokasiPembayaran', '', function (NestedForm $form) {
                $invoice = $form->select('id_penjualan', 'No. Transaksi')->setGroupClass('w-200px');
                $url = route(admin_get_route('ajax.penjualan'));
                $urlDetailInvoice = route(admin_get_route('ajax.penjualan-detail'));
                $selectAjaxInvoice = <<<JS
                    $("{$invoice->getElementClassSelector()}").select2({
                        ajax: {
                            url: "$url",
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                            return {
                                q: params.term,
                                page: params.page,
                                id_customer: idCustomer
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
                    $("{$invoice->getElementClassSelector()}").on('select2:select', function (e) {
                        const kode = e.params.data.id;
                        $.ajax({
                            url: '$urlDetailInvoice',
                            type: 'GET',
                            data: {
                                id_penjualan: kode,
                                id_customer: idCustomer
                            },
                            success: function(data) {
                                // Jika permintaan berhasil
                                console.log('Data berhasil diterima:', data);
                                const row = $(e.target).closest('tr');
                                if (data) {
                                    row.find('[name*="tanggaltempo"]').val(data.tanggaltempo);
                                    row.find('[name*="grandtotal"]').val(data.grandtotal);
                                    row.find('[name*="sisatagihan"]').val(data.sisatagihan);
                                }
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
                    $('.nominalbayar').change(function () {
                        let total = 0;
                        $('.nominalbayar').each(function () {
                            total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                        });
                        console.info(total); 
                        $('input.total').val(total);
                    });
                JS;
                $invoice->setScript($selectAjaxInvoice);
                $form->date('tanggaltempo', 'Tempo')->disable();
                $form->currency('grandtotal', 'Grand total')->symbol('Rp')->disable();
                $form->currency('sisatagihan', 'Sisa tagihan')->symbol('Rp')->disable();
                $form->date('tanggal', 'Tanggal')->default(date('Y-m-d H:i:s'))->disable();
                $form->currency('nominalbayar', 'Jumlah bayar')->symbol('Rp')->setGroupClass('w-200px');

            })->useTable();
        });
        $form->column(12, function (Form $form) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->setWidth(4);
        });
        return $form;
    }
    public function editPaymentForm($idPembayaran, $model)
    {
        $form = new Form($model);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('sales.down-payment.update'), ['idPembayaran' => $idPembayaran]));
        $data = $form->model()->with(['penjualanAlokasiPembayaran.penjualan' => function ($q) {
            $q->addSelect(DB::raw('*,toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no) as sisatagihan'));
        }])->addSelect('*', DB::raw('toko_griyanaura.f_getsisapembayaranpenjualan(transaksi_no) as sisapembayaran'))->findOrFail($idPembayaran);
        if ($data->jenisbayar != 'DP') {
            \abort(404);
        }
        $form->tools(function (Tools $tools) use ($idPembayaran, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('sales.down-payment.delete'), ['idPembayaran' => $idPembayaran]), listPath: route(admin_get_route('sales.down-payment.create'))));
            $tools->append($tools->renderView(route(admin_get_route('sales.down-payment.detail'), ['idPembayaran' => $idPembayaran])));
            $tools->append($tools->renderList(route(admin_get_route('sales.down-payment.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Customer')->required()->ajax(route(admin_get_route('ajax.kontak-customer')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak-customer')),
                'select2' => null
            ])->disable()->value($data->id_kontak)->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2, 8)->disable()->value($data->transaksi_no);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value($data->tanggal);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totaldp', 'Nominal DP')->symbol('Rp')->value($data->nominal);
            $form->currency('sisapembayaran', 'Sisa Pembayaran')->symbol('Rp')->disable()->value($data->sisapembayaran);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('penjualanAlokasiPembayaran', '', function (NestedForm $form) {
                $data = $form->model();
                if ($data) {
                    $form->text()->disable()->withoutIcon()->default($data['penjualan']['transaksi_no'])->setGroupClass('w-200px')->setScript("
                    $('.nominalbayar').change(function () {
                            let total = 0;
                            $('.nominalbayar').each(function () {
                                total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                            });
                            console.info(total); 
                            $('input.total').val(total);
                        });
                    ");
                } else {
                    $invoice = $form->select('id_penjualan', 'No. Transaksi')->setGroupClass('w-200px')->attribute([
                        'data-url' => route(admin_get_route('ajax.penjualan')),
                        'select2' => null
                    ])->default($data['id_penjualaninvoice'] ?? null);
                    $url = route(admin_get_route('ajax.penjualan'));
                    $urlDetailInvoice = route(admin_get_route('ajax.penjualan-detail'));
                    $selectAjaxInvoice = <<<JS
                        $("{$invoice->getElementClassSelector()}").select2({
                            ajax: {
                                url: "$url",
                                dataType: 'json',
                                delay: 250,
                                data: function (params) {
                                return {
                                    q: params.term,
                                    page: params.page,
                                    id_customer: idCustomer
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
                        $("{$invoice->getElementClassSelector()}").on('select2:select', function (e) {
                            const kode = e.params.data.id;
                            $.ajax({
                                url: '$urlDetailInvoice',
                                type: 'GET',
                                data: {
                                    id_penjualan: kode,
                                    id_customer: idCustomer
                                },
                                success: function(data) {
                                    // Jika permintaan berhasil
                                    console.log('Data berhasil diterima:', data);
                                    const row = $(e.target).closest('tr');
                                    if (data) {
                                        row.find('[name*="tanggaltempo"]').val(data.tanggaltempo);
                                        row.find('[name*="grandtotal"]').val(data.grandtotal);
                                        row.find('[name*="sisatagihan"]').val(data.sisatagihan);
                                    }
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
                        $('.nominalbayar').change(function () {
                            let total = 0;
                            $('.nominalbayar').each(function () {
                                total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                            });
                            console.info(total); 
                            $('input.total').val(total);
                        });
                    JS;
                    $invoice->setScript($selectAjaxInvoice);
                }
                $form->date('tanggaltempo', 'Tempo')->disable()->default($data['penjualan']['tanggaltempo'] ?? null);
                $form->currency('grandtotal', 'Grand total')->symbol('Rp')->disable()->default($data['penjualan']['grandtotal'] ?? null);
                $form->currency('sisatagihan', 'Sisa tagihan')->symbol('Rp')->disable()->default($data['penjualan']['sisatagihan'] ?? null);
                $form->date('tanggaltransaksi', 'Tanggal transaksi')->default($data['tanggal'] ?? date('Y-m-d'))->disable();
                $form->currency('nominalbayar', 'Jumlah bayar')->symbol('Rp')->setGroupClass('w-200px')->default($data['nominal'] ?? null);

            })->value($data->penjualanAlokasiPembayaran->toArray())->useTable();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('total', 'Total')->symbol('Rp')->disable()->setWidth(2, 8);
            $form->textarea('catatan')->setWidth(4)->value($data->catatan);
        });
        return $form;
    }
    public function detailPaymentForm($idPembayaran, $model)
    {
        $form = new Form($model);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('sales.down-payment.update'), ['idPembayaran' => $idPembayaran]));
        $data = $form->model()->with(['penjualanAlokasiPembayaran.penjualan' => function ($q) {
            $q->addSelect(DB::raw('*,toko_griyanaura.f_getsisatagihanpenjualan(transaksi_no) as sisatagihan'));
        }])->addSelect('*', DB::raw('toko_griyanaura.f_getsisapembayaranpenjualan(transaksi_no) as sisapembayaran'))->findOrFail($idPembayaran);
        if ($data->jenisbayar != 'DP') {
            \abort(404);
        }
        $form->tools(function (Tools $tools) use ($idPembayaran, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('sales.down-payment.delete'), ['idPembayaran' => $idPembayaran]), listPath: route(admin_get_route('sales.down-payment.create'))));
            $tools->append($tools->renderEdit(route(admin_get_route('sales.down-payment.edit'), ['idPembayaran' => $idPembayaran])));
            $tools->append($tools->renderList(route(admin_get_route('sales.down-payment.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Customer')->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $tanggal = date('d F Y', strtotime($data->tanggal));
            $form->html("<div style='padding-top: 7px;'>#{$data->transaksi_no}</div>", 'No. Transaksi')->setLabelClass(['text-nowrap'])->width('100%')->setWidth(2, 8);
            $form->html("<div style='padding-top: 7px;'>{$tanggal}</div>", 'Tanggal')->width('100%')->setWidth(2, 8);
            // $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->disable()->value($data->tanggal);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totaldp', 'Nominal DP')->symbol('Rp')->disable()->value($data->nominal);
            $form->currency('sisapembayaran', 'Sisa Pembayaran')->symbol('Rp')->disable()->value($data->sisapembayaran);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('penjualanAlokasiPembayaran', '', function (NestedForm $form) {
                $data = $form->model();
                $invoice = $form->text('No. Transaksi')->setGroupClass('w-200px')->default($data['penjualan']['transaksi_no'] ?? null)->withoutIcon()->disable();
                $form->date('tanggaltempo', 'Tempo')->default($data['penjualan']['tanggaltempo'] ?? null)->disable();
                $form->currency('grandtotal', 'Grand total')->symbol('Rp')->default($data['penjualan']['grandtotal'] ?? null)->disable();
                $form->currency('sisatagihan', 'Sisa tagihan')->symbol('Rp')->default($data['penjualan']['sisatagihan'] ?? null)->disable();
                $form->date('tanggal', 'Tanggal')->default(date('Y-m-d', strtotime($data['tanggal'] ?? null)))->disable();
                $form->currency('nominalbayar', 'Jumlah bayar')->symbol('Rp')->setGroupClass('w-200px')->default($data['nominal'] ?? null)->disable();

            })->disableCreate()->disableDelete()->value($data->penjualanAlokasiPembayaran->toArray())->useTable();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->disable()->setWidth(4)->value($data->catatan);
        });
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableSubmit();
        $form->disableReset();
        return $form;
    }

    public function createPayment(Content $content)
    {
        $style = <<<CSS
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
        CSS;
        Admin::style($style);
        $urlGetDetailProduk = route(admin_get_route('ajax.produk-detail'));
        $scriptDereferred = <<<JS
            let idCustomer = null;
            $('select.id_kontak').change(function () {
                idCustomer = $('select.id_kontak').val();
            });
            $('#has-many-penjualanAlokasiPembayaran').on('click', '.remove', function () {
                let total = 0;
                $('.nominalbayar').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
        JS;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Penjualan Pembayaran DP')
            ->description('Buat')
            ->body($this->createPaymentForm(new PenjualanPembayaran()));
    }
    public function editPayment(Content $content, $idPembayaran) 
    {
        $style = <<<CSS
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
        CSS;
        Admin::style($style);
        $urlGetDetailProduk = route(admin_get_route('ajax.produk-detail'));
        $scriptDereferred = <<<JS
            let idCustomer = $('select.id_kontak').data('value');
            $('select.id_kontak').change(function () {
                idCustomer = $('select.id_kontak').val();
            });
            $('[select2]').each(function () {
                const select = this;
                const defaultValue = select.dataset.value.split(',');
                const defaultUrl = select.dataset.url;
                defaultValue.forEach(function (value) {
                    $.ajax({
                        type: 'GET',
                        url: defaultUrl + '?id=' + value + '&id_customer=' + idCustomer
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
            $('#has-many-penjualanAlokasiPembayaran').on('click', '.remove', function () {
                let total = 0;
                $('.nominalbayar:visible').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
            {
                let total = 0;
                $('.nominalbayar').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            }
        JS;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Penjualan Pembayaran DP')
            ->description('Ubah')
            ->body($this->editPaymentForm($idPembayaran, new PenjualanPembayaran()));
    }
    public function detailPayment(Content $content, $idPembayaran) 
    {
        $style = <<<CSS
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
        CSS;
        Admin::style($style);
        $urlGetDetailProduk = route(admin_get_route('ajax.produk-detail'));
        $scriptDereferred = <<<JS
            let idCustomer = $('select.id_kontak').data('value');
            $('select.id_kontak').change(function () {
                idCustomer = $('select.id_kontak').val();
            });
            $('[select2]').each(function () {
                const select = this;
                const defaultValue = select.dataset.value.split(',');
                const defaultUrl = select.dataset.url;
                defaultValue.forEach(function (value) {
                    $.ajax({
                        type: 'GET',
                        url: defaultUrl + '?id=' + value + '&id_customer=' + idCustomer
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
            $('#has-many-penjualanAlokasiPembayaran').on('click', '.remove', function () {
                let total = 0;
                $('.nominalbayar:visible').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
            {
                let total = 0;
                $('.nominalbayar').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            }
        JS;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Penjualan Pembayaran DP')
            ->description('Ubah')
            ->body($this->detailPaymentForm($idPembayaran, new PenjualanPembayaran()));
    }

    public function storePayment(Request $request) 
    {
        try {
            $result = $this->salesDownPaymentService->storePayment($request->all());;
            admin_toastr('Sukses buat transaksi pembayaran');
            return redirect()->route(admin_get_route('sales.down-payment.detail'), ['idPembayaran' => $result->id_penjualanpembayaran]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (SalesPaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            // return [
            //     'status' => false,
            //     'then' => ['action' => 'refresh', 'value' => true],
            //     'message' => $e->getMessage()
            // ];
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function updatePayment(Request $request, $idPembayaran) 
    {
        try {
            $result = $this->salesDownPaymentService->updatePayment($idPembayaran, $request->all());;
            admin_toastr('Sukses ubah transaksi pembayaran');
            return redirect()->route(admin_get_route('sales.down-payment.edit'), ['idPembayaran' => $result->id_penjualanpembayaran]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (SalesPaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            // return [
            //     'status' => false,
            //     'then' => ['action' => 'refresh', 'value' => true],
            //     'message' => $e->getMessage()
            // ];
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function deletePayment(Request $request, $idPembayaran) 
    {
        try {
            $this->salesDownPaymentService->deletePayment($idPembayaran);
            admin_toastr('Sukses hapus pembayaran penjualan');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus pembayaran penjualan'
            ];
        } catch (SalesPaymentException $e) {
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
