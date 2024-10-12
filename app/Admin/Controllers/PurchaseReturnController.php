<?php

namespace App\Admin\Controllers;

use App\Exceptions\PurchaseReturnException;
use App\Models\PembelianRetur;
use App\Services\Core\Purchase\PurchaseReturnService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\Layout\Column;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends AdminController
{
    protected $purchaseReturnService;
    public function __construct(PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReturnService = $purchaseReturnService;
    }
    public function createReturnForm($model)
    {
        $form = new Form($model);
        $form->builder()->setTitle('Retur');
        $form->setAction(route(admin_get_route('purchase.return.store')));
        $form->text('transaksi_no')->withoutIcon()->placeholder('[AUTO]');
        $form->select('id_kontak', 'Supplier')->setWidth(2)->ajax(route(admin_get_route('ajax.kontak')));
        $invoice = $form->select('id_pembelian', 'No. Transaksi')->setWidth(2);
        $url = route(admin_get_route('ajax.pembelian'));
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
        $form->textarea('catatan')->setWidth(4);
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function editAllocateRemainingReturnFund($model, $idRetur)
    {
        $form = new Form($model);
        $form->builder()->setTitle('Alokasi Kembalian Dana Retur')->setMode('edit');
        $form->tools(function ($tools) {
            $tools->disableList();
            $tools->disableDelete();
            $tools->disableView();
        });
        $form->setAction(route(admin_get_route('purchase.return.update-allocate'), ['idRetur' => $idRetur]));
        $data = $form->model()->with(['kontak', 'pembelianReturDetail', 'pembelianReturAlokasiKembalianDana.pembelianPembayaran' => function ($q) {
            $q->addSelect('*', DB::raw('toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'));
        }])->findOrFail($idRetur);
        $form->html('<div class="modal-kembalian-dana-retur">')->plain();
        $url = route(admin_get_route('purchase.return.update-allocate'), [$idRetur]);
        $form->tablehasmany('pembelianReturAlokasiKembalianDana', '', function (NestedForm $form) use ($data) {
            $row = $form->model();
            if ($row) {
                $form->html($row['pembelian_pembayaran']['transaksi_no'], 'Pembayaran');
            } else {
                $payment = $form->select('id_pembelianpembayaran', 'Pembayaran')->setGroupClass('w-200px')->attribute([
                    'data-url' => route(admin_get_route('ajax.pembelian-pembayaran')),
                    'select2' => null
                ])->default($row['pembelian_pembayaran']['id_pembelianpembayaran'] ?? null);
                $url = route(admin_get_route('ajax.pembelian-pembayaran'));
                $urlDetailPayment = route(admin_get_route('ajax.pembelian-pembayaran-detail'));
                $selectAjaxPayment = <<<SCRIPT
                    $("{$payment->getElementClassSelector()}").select2({
                        ajax: {
                            url: "$url",
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                            return {
                                q: params.term,
                                page: params.page,
                                id_supplier: "{$data['id_kontak']}"
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
                                id_supplier: "{$data['id_kontak']}"
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
                    $('.pembelianReturAlokasiKembalianDana.nominal').change(function () {
                        let allocated = 0;
                        $('.pembelianReturAlokasiKembalianDana.nominal:visible').each(function () {
                            allocated += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                        });
                        console.info(allocated);
                        const kembalianDana = $('[name="kembaliandana"]').first().inputmask('unmaskedvalue');
                        $('[name="sisakembaliandana"]').val(kembalianDana - allocated);
                    });
                SCRIPT;
                $payment->setScript($selectAjaxPayment);
            }
            $form->currency('nominalpembayaran', 'Pembayaran awal')->disable()->symbol('Rp')->default($row['pembelian_pembayaran']['nominal'] ?? null);
            $form->currency('sisapembayaran', 'Sisa pembayaran')->disable()->symbol('Rp')->default($row['pembelian_pembayaran']['sisapembayaran'] ?? null);
            $form->currency('nominal', 'Nominal alokasi')->symbol('Rp');
        })->useTable()->value($data->pembelianReturAlokasiKembalianDana->toArray());
        $form->currency('sisakembaliandana', 'Sisa kembalian dana')->setWidth(2, 8)->width('100%')->setGroupClass('mt-4')->symbol('Rp')->readonly()->value($data->kembaliandana);
        $form->html('</div>')->plain();
        $form->disableReset();
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function editReturnForm($model, $idRetur)
    {
        $form = new Form($model);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('purchase.return.update'), ['idRetur' => $idRetur]));
        $data = $form->model()->with(['pembelian', 'pembelianDetail' => function ($q) {
            $q->leftJoin(DB::raw("(select id_pembeliandetail as id_pembeliandetaildiretur, sum(qty) as jumlah_diretur from toko_griyanaura.tr_pembelianreturdetail group by id_pembeliandetail) as x"), 'x.id_pembeliandetaildiretur', 'toko_griyanaura.tr_pembeliandetail.id_pembeliandetail');
            $q->join(DB::raw("(select kode_produkvarian, id_produk from toko_griyanaura.ms_produkvarian) as y"), 'y.kode_produkvarian', 'toko_griyanaura.tr_pembeliandetail.kode_produkvarian');
            $q->join(DB::raw("(select id_produk, in_stok from toko_griyanaura.ms_produk) as z"), 'z.id_produk', 'y.id_produk');
            $q->where('z.in_stok', true);
        }, 'kontak', 'pembelianReturDetail', 'pembelianReturAlokasiKembalianDana.pembelianPembayaran' => function ($q) {
            $q->addSelect('*', DB::raw('toko_griyanaura.f_getsisapembayaran(transaksi_no) as sisapembayaran'));
        }])->findOrFail($idRetur);
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>{$data->kontak->nama} - {$data->kontak->alamat}</div>", 'Supplier')->setWidth(3);
            $form->html("<div style='padding-top: 7px'>#{$data->pembelian->transaksi_no}</div>", 'Invoice yang diretur')->setWidth(3);
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->html("<div style='padding-top: 7px'>#{$data->transaksi_no}</div>", 'No. Transaksi')->setWidth(2, 8);
            $form->datetime('tanggal', 'Tanggal')->required()->width('100%')->setWidth(2, 8)->value($data->tanggal);
        });
        $form->column(12, function (Form $form) use ($data) {
            $returItem = $data->pembelianReturDetail->keyBy('id_pembeliandetail');
            $form->tablehasmany('pembelianDetail', 'Detail retur', function (NestedForm $form) use ($returItem) {
                $data = $form->model();
                $form->html($data?->produkVarian?->varian, 'Produk')->required();
                $form->html(number($data?->jumlah_diretur - ($returItem[$data?->id_pembeliandetail]['qty'] ?? 0) ?: 0) . ' / ' . number($data?->qty), 'Qty')->setGroupClass('w-50px');
                $form->text('qty_diretur', '')->default(number($returItem[$data?->id_pembeliandetail]['qty'] ?? null))->attribute([
                    'type' => 'number',
                    'max' => $data?->qty - ($data?->jumlah_diretur - ($returItem[$data?->id_pembeliandetail]['qty'] ?? 0)),
                    'min' => 0
                ])->withoutIcon()->required()->setGroupClass('w-100px');
                $form->currency('harga', 'Harga')->disable()->required()->symbol('Rp');
                $form->currency('diskon', 'Diskon')->disable()->symbol('%');
                $form->currency('total', 'Total')->disable()->symbol('Rp')->readonly();
                $form->hidden('id_pembelianreturdetail')->default($returItem[$data?->id_pembeliandetail]['id_pembelianreturdetail'] ?? null);
            })->value($data->pembelianDetail->toArray())->useTable()->disableCreate()->disableDelete();
        });
        $form->column(12, function (Form $form) use ($data) {
            $form->currency('totalraw', 'Sub total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->totalraw);
            $form->currency('diskon')->setWidth(2, 8)->width('100%')->symbol('%')->readonly()->value($data->diskon);
            $form->currency('total', 'Total')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->grandtotal);
            $form->currency('kembaliandana', 'Kembalian Dana')->setWidth(2, 8)->width('100%')->symbol('Rp')->readonly()->value($data->kembaliandana);
            $form->textarea('catatan')->setWidth(4)->value($data->catatan);
        });
        $form->disableReset();
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function detailReturnForm() {}

    public function createReturn(Content $content)
    {
        $scriptDereferred = <<<JS
            let idSupplier = null;
            $('select.id_kontak').change(function () {
                idSupplier = $('select.id_kontak').val();
            });
        JS;
        Admin::script($scriptDereferred, true);
        return $content
            ->title('Retur Pembelian')
            ->description('Buat')
            ->body($this->createReturnForm(new PembelianRetur()));
    }
    public function editReturn(Content $content, $idRetur)
    {
        $style = <<<CSS
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
        $dereferredScript = <<<JS
            $('input.qty_diretur').on('change', function () {
                let rawTotal = 0;
                $('#has-many-pembelianDetail tbody tr').each(function () {
                    const qty = $(this).find('.qty_diretur').inputmask('unmaskedvalue');
                    const harga = $(this).find('.harga').inputmask('unmaskedvalue');
                    const diskonProduk = $(this).find('.diskon').inputmask('unmaskedvalue');
                    console.info(qty, harga, diskonProduk);
                    const total = qty*harga*(1-diskonProduk/100);
                    rawTotal += total;
                });
                $('.totalraw').val(rawTotal);
                const diskon = $('[name="diskon"]').val();
                $('[name="total"]').val(rawTotal * (1-diskon/100));
            });
            let allocated = 0;
            $('.pembelianReturAlokasiKembalianDana.nominal:visible').each(function () {
                allocated += parseInt($(this).inputmask('unmaskedvalue')) || 0;
            });
            const kembalianDana = parseInt($('[name="kembaliandana"]').first().inputmask('unmaskedvalue')) || 0;
            $('[name="sisakembaliandana"]').val(kembalianDana - allocated);
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
            $('#has-many-pembelianReturAlokasiKembalianDana').on('click', '.remove', function () {
                let allocated = 0;
                $('.pembelianReturAlokasiKembalianDana.nominal:visible').each(function () {
                    allocated += parseInt($(this).inputmask('unmaskedvalue')) || 0;
                });
                console.info(allocated);
                const kembalianDana = parseInt($('[name="kembaliandana"]').first().inputmask('unmaskedvalue')) || 0;
                $('[name="sisakembaliandana"]').val(kembalianDana - allocated);
            });
        JS;
        Admin::script($dereferredScript, true);
        return $content
            ->title('Retur Pembelian')
            ->description('edit')
            ->row(function (Row $row) use ($idRetur) {
                $row->column(12, $this->editReturnForm(new PembelianRetur, $idRetur));
                $row->column(12, $this->editAllocateRemainingReturnFund(new PembelianRetur, $idRetur));
            });
        // ->body($this->editReturnForm(new PembelianRetur, $idRetur));
    }
    public function detailReturn(Content $content, $idRetur) {}

    public function storeReturn(Request $request)
    {
        try {
            $result = $this->purchaseReturnService->storeReturn($request->all());;
            admin_toastr('Sukses');
            return redirect()->route(admin_get_route('purchase.return.edit'), ['idRetur' => $result->id_pembelianretur]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchaseReturnException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function updateReturn(Request $request, $idRetur)
    {
        try {
            $result = $this->purchaseReturnService->updateReturn($idRetur, $request->all());
            admin_toastr('Sukses ubah transaksi retur pembelian');
            return redirect()->route(admin_get_route('purchase.return.edit'), ['idRetur' => $result->id_pembelianretur]);
        } catch (ValidationException $e) {
            return $e->validator->getMessageBag();
        } catch (PurchaseReturnException $e) {
            admin_toastr($e->getMessage(), 'warning');
            return redirect()->back();
        } catch (QueryException $e) {
            admin_toastr($e->getPrevious()->getMessage(), 'warning');
            return redirect()->back();
        } catch (\Exception $e) {
            admin_toastr('Internal Server Error', 'error');
            return redirect()->back();
        }
    }
    public function deleteReturn(Request $request, $idRetur) {}
    public function updateAllocate(Request $request, $idRetur) 
    {
        dump($request->all());
        admin_toastr('Berhasil hit!', 'info');
        return redirect()->back();
    }
}
