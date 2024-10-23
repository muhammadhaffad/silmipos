<?php
namespace App\Admin\Controllers;

use App\Exceptions\PurchasePaymentException;
use App\Models\PembelianRefund;
use App\Services\Core\Purchase\PurchaseRefundPaymentService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Layout\Content;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRefundPaymentController extends AdminController
{
    protected $purchaseRefundPaymentService;
    public function __construct(PurchaseRefundPaymentService $purchaseRefundPaymentService)
    {
        $this->purchaseRefundPaymentService = $purchaseRefundPaymentService;
    }
    public function createRefundForm($model)
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.refund.store')));
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak-supplier')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2, 8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
        });
        $form->column(12, function (Form $form) {
            $form->tablehasmany('pembelianRefundDetail', '', function (NestedForm $form) {
                $payment = $form->select('id_pembelianpembayaran', 'No. Transaksi')->setGroupClass('w-200px');
                $url = route(admin_get_route('ajax.pembelian-pembayaran'));
                $urlDetailPayment = route(admin_get_route('ajax.pembelian-pembayaran-detail'));
                $selectAjaxInvoice = <<<SCRIPT
                    $("{$payment->getElementClassSelector()}").select2({
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
                    $("{$payment->getElementClassSelector()}").on('select2:select', function (e) {
                        const kode = e.params.data.id;
                        $.ajax({
                            url: '$urlDetailPayment',
                            type: 'GET',
                            data: {
                                id_pembelianpembayaran: kode,
                                id_supplier: idSupplier
                            },
                            success: function(data) {
                                // Jika permintaan berhasil
                                console.log('Data berhasil diterima:', data);
                                const row = $(e.target).closest('tr');
                                if (data) {
                                    row.find('[name*="nominalpembayaran"]').val(data.nominal);
                                    row.find('[name*="sisapembayaran"]').val(data.sisapembayaran);
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
                    $('.nominal').change(function () {
                        let total = 0;
                        $('.nominal').each(function () {
                            total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                        });
                        console.info(total); 
                        $('input.total').val(total);
                    });
                SCRIPT;
                $payment->setScript($selectAjaxInvoice);
                $form->currency('nominalpembayaran', 'Nominal DP awal')->symbol('Rp')->disable();
                $form->currency('sisapembayaran', 'Sisa pembayaran')->symbol('Rp')->disable();
                $form->currency('nominal', 'Jumlah refund')->symbol('Rp')->setGroupClass('w-200px');

            })->useTable();
        });
        $form->column(12, function (Form $form) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->setWidth(4);
        });
        return $form;
    }
    public function editRefundForm($idRefund, $model)
    {
        $form = new Form($model);
        $data = $form->model()->with(['pembelianRefundDetail.pembelianPembayaranDP' => function ($q) {
            $q->addSelect(DB::raw('*,toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'));
        }])->findOrFail($idRefund);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('purchase.refund.update'), ['idRefund' => $idRefund]));
        $form->tools(function (Tools $tools) use ($idRefund, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.refund.delete'), ['idRefund' => $idRefund]), listPath: route(admin_get_route('purchase.refund.create'))));
            $tools->append($tools->renderView(route(admin_get_route('purchase.refund.detail'), ['idRefund' => $idRefund])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak-supplier')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak-supplier')),
                'select2' => null
            ])->disable()->value($data->id_kontak)->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2, 8)->disable()->value($data->transaksi_no);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('pembelianRefundDetail', '', function (NestedForm $form) {
                $row = $form->model();
                if ($row) {
                    $form->html($row['pembelian_pembayaran_d_p']['transaksi_no']);
                } else {
                    $payment = $form->select('id_pembelianpembayaran', 'No. Transaksi')->setGroupClass('w-200px');
                    $url = route(admin_get_route('ajax.pembelian-pembayaran'));
                    $urlDetailPayment = route(admin_get_route('ajax.pembelian-pembayaran-detail'));
                    $selectAjaxInvoice = <<<SCRIPT
                        $("{$payment->getElementClassSelector()}").select2({
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
                        $("{$payment->getElementClassSelector()}").on('select2:select', function (e) {
                            const kode = e.params.data.id;
                            $.ajax({
                                url: '$urlDetailPayment',
                                type: 'GET',
                                data: {
                                    id_pembelianpembayaran: kode,
                                    id_supplier: idSupplier
                                },
                                success: function(data) {
                                    // Jika permintaan berhasil
                                    console.log('Data berhasil diterima:', data);
                                    const row = $(e.target).closest('tr');
                                    if (data) {
                                        row.find('[name*="nominalpembayaran"]').val(data.nominal);
                                        row.find('[name*="sisapembayaran"]').val(data.sisapembayaran);
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
                        $('.nominal').change(function () {
                            let total = 0;
                            $('.nominal').each(function () {
                                total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                            });
                            console.info(total); 
                            $('input.total').val(total);
                        });
                    SCRIPT;
                    $payment->setScript($selectAjaxInvoice);
                }
                $form->currency('nominalpembayaran', 'Nominal DP awal')->default($row['pembelian_pembayaran_d_p']['nominal'] ?? null)->symbol('Rp')->disable();
                $form->currency('sisapembayaran', 'Sisa pembayaran')->default($row['pembelian_pembayaran_d_p']['sisapembayaran'] ?? null)->symbol('Rp')->disable();
                $form->currency('nominal', 'Jumlah refund')->symbol('Rp')->setGroupClass('w-200px');

            })->useTable()->value($data->pembelianRefundDetail->toArray());
        });
        $form->column(12, function (Form $form) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->setWidth(4);
        });
        return $form;
    }
    public function detailRefundForm($idRefund, $model)
    {
        $form = new Form($model);
        $data = $form->model()->with(['pembelianRefundDetail.pembelianPembayaranDP' => function ($q) {
            $q->addSelect(DB::raw('*,toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'));
        }])->findOrFail($idRefund);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('purchase.refund.update'), ['idRefund' => $idRefund]));
        $form->tools(function (Tools $tools) use ($idRefund, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            
            $tools->append($tools->renderDelete(route(admin_get_route('purchase.refund.delete'), ['idRefund' => $idRefund]), listPath: route(admin_get_route('purchase.refund.create'))));
            $tools->append($tools->renderEdit(route(admin_get_route('purchase.refund.edit'), ['idRefund' => $idRefund])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak-supplier')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak-supplier')),
                'select2' => null
            ])->disable()->value($data->id_kontak)->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2, 8)->disable()->value($data->transaksi_no);
            $form->datetime('tanggal', 'Tanggal')->disable()->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->tablehasmany('pembelianRefundDetail', '', function (NestedForm $form) {
                $row = $form->model();
                if ($row) {
                    $form->html($row['pembelian_pembayaran_d_p']['transaksi_no']);
                } else {
                    $payment = $form->select('id_pembelianpembayaran', 'No. Transaksi')->setGroupClass('w-200px');
                    $url = route(admin_get_route('ajax.pembelian-pembayaran'));
                    $urlDetailPayment = route(admin_get_route('ajax.pembelian-pembayaran-detail'));
                    $selectAjaxInvoice = <<<SCRIPT
                        $("{$payment->getElementClassSelector()}").select2({
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
                        $("{$payment->getElementClassSelector()}").on('select2:select', function (e) {
                            const kode = e.params.data.id;
                            $.ajax({
                                url: '$urlDetailPayment',
                                type: 'GET',
                                data: {
                                    id_pembelianpembayaran: kode,
                                    id_supplier: idSupplier
                                },
                                success: function(data) {
                                    // Jika permintaan berhasil
                                    console.log('Data berhasil diterima:', data);
                                    const row = $(e.target).closest('tr');
                                    if (data) {
                                        row.find('[name*="nominalpembayaran"]').val(data.nominal);
                                        row.find('[name*="sisapembayaran"]').val(data.sisapembayaran);
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
                        $('.nominal').change(function () {
                            let total = 0;
                            $('.nominal').each(function () {
                                total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                            });
                            console.info(total); 
                            $('input.total').val(total);
                        });
                    SCRIPT;
                    $payment->setScript($selectAjaxInvoice);
                }
                $form->currency('nominalpembayaran', 'Nominal DP awal')->default($row['pembelian_pembayaran_d_p']['nominal'] ?? null)->symbol('Rp')->disable();
                $form->currency('sisapembayaran', 'Sisa pembayaran')->default($row['pembelian_pembayaran_d_p']['sisapembayaran'] ?? null)->symbol('Rp')->disable();
                $form->currency('nominal', 'Jumlah refund')->disable()->symbol('Rp')->setGroupClass('w-200px');

            })->disableDelete()->disableCreate()->useTable()->value($data->pembelianRefundDetail->toArray());
        });
        $form->column(12, function (Form $form) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->disable()->setWidth(4);
        });
        return $form;
    }

    public function createRefund(Content $content)
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
            $('#has-many-pembelianRefundDetail').on('click', '.remove', function () {
                let total = 0;
                $('.nominal').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
        SCRIPT;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Refund Pembelian Pembayaran')
            ->description('Buat')
            ->body($this->createRefundForm(new PembelianRefund()));
    }
    public function editRefund(Content $content, $idRefund)
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
        $scriptDereferred = <<<JS
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
            $('#has-many-pembelianRefundDetail').on('click', '.remove', function () {
                let total = 0;
                $('.nominal:visible').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
            {
                let total = 0;
                $('.nominal').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
                $('.nominal').on('change', function () {
                    let total = 0;
                    $('.nominal').each(function () {
                        total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                    });
                    console.info(total); 
                    $('input.total').val(total);
                });
            }
        JS;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Refund Pembelian Pembayaran')
            ->description('Ubah')
            ->body($this->editRefundForm($idRefund, new PembelianRefund()));
    }
    public function detailRefund(Content $content, $idRefund)
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
        $scriptDereferred = <<<JS
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
            $('#has-many-pembelianRefundDetail').on('click', '.remove', function () {
                let total = 0;
                $('.nominal:visible').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
            });
            {
                let total = 0;
                $('.nominal').each(function () {
                    total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(total); 
                $('input.total').val(total);
                $('.nominal').on('change', function () {
                    let total = 0;
                    $('.nominal').each(function () {
                        total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                    });
                    console.info(total); 
                    $('input.total').val(total);
                });
            }
        JS;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Refund Pembelian Pembayaran')
            ->description('Ubah')
            ->body($this->detailRefundForm($idRefund, new PembelianRefund()));
    }

    public function storeRefund(Request $request)
    {
        try {
            $result = $this->purchaseRefundPaymentService->storeRefundDP($request->all());
            admin_toastr('Sukses');
            return redirect()->route(admin_get_route('purchase.refund.edit'), ['idRefund' => $result->id_pembelianrefund]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchasePaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function updateRefund($idRefund, Request $request)
    {
        try {
            $result = $this->purchaseRefundPaymentService->updateRefundDP($idRefund, $request->all());
            admin_toastr('Sukses update refund pembelian');
            // return redirect()->route(admin_get_route('purchase.return.edit'), ['idRetur' => $result->id_pembelianretur]);
            return redirect()->back();
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchasePaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function deleteRefund(Request $request, $idRefund) 
    {
        try {
            $this->purchaseRefundPaymentService->deleteRefundDP($idRefund);
            admin_toastr('Sukses hapus refund pembayaran pembelian');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus refund pembayaran pembelian'
            ];
        } catch (PurchasePaymentException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return [
                'status' => false,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}