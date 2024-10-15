<?php
namespace App\Admin\Controllers;

use App\Models\PembelianRefund;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Layout\Content;

class PurchaseRefundPaymentController extends AdminController
{
    public function createRefundForm($model)
    {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.payment.store')));
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak')))->setWidth(3);
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
                    $('.nominalbayar').change(function () {
                        let total = 0;
                        $('.nominalbayar').each(function () {
                            total += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                        });
                        console.info(total); 
                        $('input.total').val(total);
                    });
                SCRIPT;
                $payment->setScript($selectAjaxInvoice);
                $form->currency('nominalpembayaran', 'Nominal DP awal')->symbol('Rp')->disable();
                $form->currency('sisapembayaran', 'Sisa pembayaran')->symbol('Rp')->disable();
                $form->currency('nominalbayar', 'Jumlah refund')->symbol('Rp')->setGroupClass('w-200px');

            })->useTable();
        });
        $form->column(12, function (Form $form) {
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly();
            $form->textarea('catatan')->setWidth(4);
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
            ->title('Refund Pembelian Pembayaran')
            ->description('Buat')
            ->body($this->createRefundForm(new PembelianRefund()));
    }
}