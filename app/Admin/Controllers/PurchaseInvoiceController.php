<?php

namespace App\Admin\Controllers;

use App\Models\Pembelian;
use App\Services\Core\Purchase\PurchaseInvoiceService;
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
    public function editPurchaseInvoiceForm($model, $idPembelian) {
        $form = new Form($model);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('purchase.invoice.update'), ['idPembelian' => $idPembelian]));
        $data = $form->model()->with('pembelianDetail.produkVarian')->where('id_pembelian', $idPembelian)->first();
        $form->tools(function (Tools $tools) use ($idPembelian, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            // $tools->append($tools->renderDelete(route(admin_get_route('purchase.invoice.delete'), ['idPembelian' => $idPembelian]), listPath: route(admin_get_route('purchase.invoice.create'))));
            $tools->append($tools->renderView(route(admin_get_route('purchase.invoice.detail'), ['idPembelian' => $idPembelian])));
            $tools->append($tools->renderList(route(admin_get_route('produk-penyesuaian.list'))));
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->select('id_kontak', 'Supplier')->required()->ajax(route(admin_get_route('ajax.kontak')))->attribute([
                'data-url' => route(admin_get_route('ajax.kontak')),
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
            $form->tablehasmany('pembelianDetail', function (NestedForm $form) {
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
    public function editPurchaseInvoice(Content $content, $idPembelian) {
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
                        if (data?.porduk_persediaan) {
                            $(e.target).closest('tr').find('[name*="harga"]').val(data.porduk_persediaan[0].produk_varian_harga.hargabeli);
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
                            if (data?.porduk_persediaan) {
                                $(e.target).closest('tr').find('[name*="harga"]').val(data.porduk_persediaan[0].produk_varian_harga.hargabeli);
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
        if (!$pembelian->where('id_pembelian', $idPembelian)->where('jenis', 'invoice')->first()) {
            abort(404);
        }
        return $content
            ->title('Invoice Pembelian')
            ->description('Ubah')
            ->body($this->editPurchaseInvoiceForm($pembelian, $idPembelian));
    }
    public function detailPurchaseInvoice() {
        return 'TODO: detail';
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
    public function updatePurchaseInvoice(Request $request, $idPembelian) {
        try {
            $result = $this->purchaseInvoiceService->updatePurchaseInvoice($idPembelian, $request->all());
            admin_toastr('Sukses ubah transaksi pembelian');
            // return redirect()->route(admin_get_route('purchase.invoice.detail'), ['idPembelian' => $result->id_pembelian]);
            return redirect()->route(admin_get_route('purchase.invoice.create'));
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
