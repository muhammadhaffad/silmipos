<?php

namespace App\Admin\Controllers;

use App\Exceptions\PurchasePaymentException;
use App\Models\PembelianPembayaran;
use App\Services\Core\Purchase\PurchaseDownPaymentService;
use App\Services\Core\Purchase\PurchasePaymentService;
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

class PurchaseDownPaymentController extends AdminController
{
    protected $purchaseDownPaymentService;
    public function __construct(PurchaseDownPaymentService $purchaseDownPaymentService)
    {
        $this->purchaseDownPaymentService = $purchaseDownPaymentService;
    }
    public function createPaymentForm($model)
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.down-payment.store')));
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2, 8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
        });
        
        $form->column(12, function (Form $form) {
            $form->currency('totaldp', 'Nominal DP')->symbol('Rp');
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('pembelianAlokasiPembayaran', '', function (NestedForm $form) {
                $invoice = $form->select('id_pembelian', 'No. Transaksi')->setGroupClass('w-200px');
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
                    $("{$invoice->getElementClassSelector()}").on('select2:select', function (e) {
                        const kode = e.params.data.id;
                        $.ajax({
                            url: '$urlDetailInvoice',
                            type: 'GET',
                            data: {
                                id_pembelian: kode,
                                id_supplier: idSupplier
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
                SCRIPT;
                $invoice->setScript($selectAjaxInvoice);
                $form->date('tanggaltempo', 'Tempo')->disable();
                $form->currency('grandtotal', 'Grand total')->symbol('Rp')->disable();
                $form->currency('sisatagihan', 'Sisa tagihan')->symbol('Rp')->disable();
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
        $form->setAction(route(admin_get_route('purchase.down-payment.update'), ['idPembayaran' => $idPembayaran]));
        $data = $form->model()->with(['pembelianAlokasiPembayaran.pembelian' => function ($q) {
            $q->addSelect(DB::raw('*,toko_griyanaura.f_getsisatagihan(transaksi_no) as sisatagihan'));
        }])->addSelect('*', DB::raw('toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'))->findOrFail($idPembayaran);
        if ($data->jenisbayar != 'DP') {
            \abort(404);
        }
        $form->tools(function (Tools $tools) use ($idPembayaran, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.down-payment.delete'), ['idPembayaran' => $idPembayaran]), listPath: route(admin_get_route('purchase.down-payment.create'))));
            $tools->append($tools->renderView(route(admin_get_route('purchase.down-payment.detail'), ['idPembayaran' => $idPembayaran])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak')),
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
            $form->tablehasmany('pembelianAlokasiPembayaran', '', function (NestedForm $form) {
                $data = $form->model();
                if ($data) {
                    $form->text()->disable()->withoutIcon()->default($data['pembelian']['transaksi_no'])->setGroupClass('w-200px')->setScript("
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
                    $invoice = $form->select('id_pembelian', 'No. Transaksi')->setGroupClass('w-200px')->attribute([
                        'data-url' => route(admin_get_route('ajax.pembelian')),
                        'select2' => null
                    ])->default($data['id_pembelianinvoice'] ?? null);
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
                        $("{$invoice->getElementClassSelector()}").on('select2:select', function (e) {
                            const kode = e.params.data.id;
                            $.ajax({
                                url: '$urlDetailInvoice',
                                type: 'GET',
                                data: {
                                    id_pembelian: kode,
                                    id_supplier: idSupplier
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
                    SCRIPT;
                    $invoice->setScript($selectAjaxInvoice);
                }
                $form->date('tanggaltempo', 'Tempo')->disable()->default($data['pembelian']['tanggaltempo'] ?? null);
                $form->currency('grandtotal', 'Grand total')->symbol('Rp')->disable()->default($data['pembelian']['grandtotal'] ?? null);
                $form->currency('sisatagihan', 'Sisa tagihan')->symbol('Rp')->disable()->default($data['pembelian']['sisatagihan'] ?? null);
                $form->currency('nominalbayar', 'Jumlah bayar')->symbol('Rp')->setGroupClass('w-200px')->default($data['nominal'] ?? null);

            })->value($data->pembelianAlokasiPembayaran->toArray())->useTable();
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
        $form->setAction(route(admin_get_route('purchase.down-payment.update'), ['idPembayaran' => $idPembayaran]));
        $data = $form->model()->with(['pembelianAlokasiPembayaran.pembelian' => function ($q) {
            $q->addSelect(DB::raw('*,toko_griyanaura.f_getsisatagihan(transaksi_no) as sisatagihan'));
        }])->addSelect('*', DB::raw('toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'))->findOrFail($idPembayaran);
        if ($data->jenisbayar != 'DP') {
            \abort(404);
        }
        $form->tools(function (Tools $tools) use ($idPembayaran, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.down-payment.delete'), ['idPembayaran' => $idPembayaran]), listPath: route(admin_get_route('purchase.down-payment.create'))));
            $tools->append($tools->renderEdit(route(admin_get_route('purchase.down-payment.edit'), ['idPembayaran' => $idPembayaran])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Supplier')->setWidth(3);
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
            $form->tablehasmany('pembelianAlokasiPembayaran', '', function (NestedForm $form) {
                $data = $form->model();
                $invoice = $form->text('No. Transaksi')->setGroupClass('w-200px')->default($data['pembelian']['transaksi_no'] ?? null)->withoutIcon()->disable();
                $form->date('tanggaltempo', 'Tempo')->default($data['pembelian']['tanggaltempo'] ?? null)->disable();
                $form->currency('grandtotal', 'Grand total')->symbol('Rp')->default($data['pembelian']['grandtotal'] ?? null)->disable();
                $form->currency('sisatagihan', 'Sisa tagihan')->symbol('Rp')->default($data['pembelian']['sisatagihan'] ?? null)->disable();
                $form->currency('nominalbayar', 'Jumlah bayar')->symbol('Rp')->setGroupClass('w-200px')->default($data['nominal'] ?? null)->disable();

            })->disableCreate()->disableDelete()->value($data->pembelianAlokasiPembayaran->toArray())->useTable();
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
            let idSupplier = null;
            $('select.id_kontak').change(function () {
                idSupplier = $('select.id_kontak').val();
            });
            $('#has-many-pembelianAlokasiPembayaran').on('click', '.remove', function () {
                let total = 0;
                $('.nominalbayar').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Pembelian Pembayaran DP')
            ->description('Buat')
            ->body($this->createPaymentForm(new PembelianPembayaran()));
    }
    public function editPayment(Content $content, $idPembayaran) 
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
            let idSupplier = $('select.id_kontak').data('value');
            $('select.id_kontak').change(function () {
                idSupplier = $('select.id_kontak').val();
            });
            $('[select2]').each(function () {
                const select = this;
                const defaultValue = select.dataset.value.split(',');
                const defaultUrl = select.dataset.url;
                defaultValue.forEach(function (value) {
                    $.ajax({
                        type: 'GET',
                        url: defaultUrl + '?id=' + value + '&id_supplier=' + idSupplier
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
            $('#has-many-pembelianAlokasiPembayaran').on('click', '.remove', function () {
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
        SCRIPT;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Pembelian Pembayaran DP')
            ->description('Ubah')
            ->body($this->editPaymentForm($idPembayaran, new PembelianPembayaran()));
    }
    public function detailPayment(Content $content, $idPembayaran) 
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
            let idSupplier = $('select.id_kontak').data('value');
            $('select.id_kontak').change(function () {
                idSupplier = $('select.id_kontak').val();
            });
            $('[select2]').each(function () {
                const select = this;
                const defaultValue = select.dataset.value.split(',');
                const defaultUrl = select.dataset.url;
                defaultValue.forEach(function (value) {
                    $.ajax({
                        type: 'GET',
                        url: defaultUrl + '?id=' + value + '&id_supplier=' + idSupplier
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
            $('#has-many-pembelianAlokasiPembayaran').on('click', '.remove', function () {
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
        SCRIPT;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Pembelian Pembayaran DP')
            ->description('Ubah')
            ->body($this->detailPaymentForm($idPembayaran, new PembelianPembayaran()));
    }

    public function storePayment(Request $request) 
    {
        try {
            $result = $this->purchaseDownPaymentService->storePayment($request->all());;
            admin_toastr('Sukses buat transaksi pembayaran');
            return redirect()->route(admin_get_route('purchase.down-payment.detail'), ['idPembayaran' => $result->id_pembelianpembayaran]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchasePaymentException $e) {
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
            $result = $this->purchaseDownPaymentService->updatePayment($idPembayaran, $request->all());;
            admin_toastr('Sukses ubah transaksi pembayaran');
            return redirect()->route(admin_get_route('purchase.down-payment.edit'), ['idPembayaran' => $result->id_pembelianpembayaran]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchasePaymentException $e) {
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
            $this->purchaseDownPaymentService->deletePayment($idPembayaran);
            admin_toastr('Sukses hapus pembayaran pembelian');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus pembayaran pembelian'
            ];
        } catch (PurchasePaymentException $e) {
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
