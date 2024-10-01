<?php

namespace App\Admin\Controllers;

use App\Models\Pembelian;
use App\Services\Core\Purchase\PurchaseInvoiceService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceController extends AdminController
{
    protected $purchaseInvoiceService;
    public function __construct(PurchaseInvoiceService $purchaseInvoiceService)
    {
        $this->purchaseInvoiceService = $purchaseInvoiceService;
    }
    public function createPurchaseInvoiceForm($model) {
        $form = new Form($model);
        $form->setAction(route(admin_get_route('purchase.invoice.store')));
        $data = $form->model();
        $form->column(12, function (Form $form) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak')))->setWidth(3);
        });
        $form->column(12, function (Form $form) {
            $form->text('transaksi_no', 'No. Transaksi')->placeholder('[AUTO]')->setLabelClass(['text-nowrap'])->withoutIcon()->width('100%')->setWidth(2,8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s'));
            $form->datetime('tanggaltempo', 'Tanggal Tempo')->required()->width('100%')->setWidth(2, 8)->value(date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . '+1 days')));
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

    public function createPurchaseInvoice(Content $content) {
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
                            if (data?.porduk_persediaan) {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.porduk_persediaan[0].produk_varian_harga.hargabeli);
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
            ->title('Invoice Pembelian')
            ->description('Buat')
            ->body($this->createPurchaseInvoiceForm($pembelian));
    }

    public function storePurchaseInvoice(Request $request) {
        try {
            $result = $this->purchaseInvoiceService->storePurchaseInvoice($request->all());
            admin_toastr('Sukses buat transaksi pembelian');
            // return redirect()->route(admin_get_route('purchase.invoice.detail'), ['idPembelian' => $result->id_pembelian]);
            return redirect()->route(admin_get_route('purchase.invoice.create'));
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
