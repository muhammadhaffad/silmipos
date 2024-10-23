<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\Delete;
use App\Admin\Actions\Grid\Edit;
use App\Admin\Actions\Grid\Show;
use App\Admin\Form\Form;
use App\Http\Controllers\Controller;
use App\Models\PindahGudang;
use App\Models\Produk;
use App\Models\ProdukPersediaanDetail;
use App\Models\ProdukVarian;
use App\Services\Core\PindahGudang\PindahGudangService;
use Encore\Admin\Admin;
use Encore\Admin\Form\Field\Button;
use Encore\Admin\Form\Field\Text;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Displayers\DropdownActions;
use Encore\Admin\Grid\Filter;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdukMutasiController extends Controller
{
    protected $pindahGudangService;
    public function __construct(PindahGudangService $pindahGudangService)
    {
        $this->pindahGudangService = $pindahGudangService;
    }
    public function listProdukMutasiGrid() {
        $grid = new Grid(new PindahGudang());
        $grid->model()->join(DB::raw("(select nama as nama_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg"), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')->join(DB::raw("(select nama as nama_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2"), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang')->where('is_batal', false)->orderBy('tanggal');
        $grid->filter(function (Filter $filter) {
            $filter->expand();
            $filter->disableIdFilter();
            $filter->ilike('transaksi_no', 'No. Transaksi');
        });
        $grid->column('transaksi_no', 'No. Transaksi')->sortable();
        $grid->column('tanggal')->display(function ($value) {
            return date('d F Y', strtotime($value));
        })->sortable();
        $grid->column('catatan')->display(function ($value) {
            if (strlen($value) > 20) {
                return substr($value, 0, 20) . '...';
            }
            return $value;
        });
        $grid->column('nama_fromgudang', 'Dari Gudang');
        $grid->column('nama_togudang', 'Ke Gudang');
        $grid->column('is_valid', 'Status')->display(function ($val) {
            return $val ? 'Valid' : 'Belum valid';
        })->label([
            true => 'success',
            false => 'warning'
        ]);
        $grid->actions(function (DropdownActions $actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
            $actions->add(new Show);
            if (!$this->row->is_valid) {
                $actions->add(new Edit());
                // dump($this);
                $actions->add(new Delete(route(admin_get_route('produk-mutasi.delete'), $this->row->id_pindahgudang)));
            }
        });
        return $grid;
    }
    public function createProdukMutasiForm() {
        $form = new Form(new PindahGudang);
        $form->text('transaksi_no')->withoutIcon()->placeholder('[AUTO]');
        $form->datetime('tanggal')->required()->default(date('Y-m-d H:i:s'));
        $form->select('from_gudang', 'Dari Gudang')->required()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4);
        $form->select('to_gudang', 'Ke Gudang')->required()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4);
        $form->text('keterangan')->setWidth(4);
        $form->textarea('catatan');
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function createProdukMutasiDetailFormGrid($idPindahGudang) {
        $form = new Form(new PindahGudang);
        $form->builder()->setMode('edit');
        $form->setAction(route(admin_get_route('produk-mutasi.validate'), ['idPindahGudang' => $idPindahGudang]));
        $data = $form->model()->where('id_pindahgudang', $idPindahGudang)
            ->join(DB::raw('(select nama as nama_fromgudang, default_varianharga as varianharga_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg'), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')
            ->join(DB::raw('(select nama as nama_togudang, default_varianharga as varianharga_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2'), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang')
            ->first();
        $form->tools(function (Tools $tools) use ($idPindahGudang, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            if (!$data->is_valid) {
                $tools->append($tools->renderDelete(route(admin_get_route('produk-mutasi.delete'), ['idPindahGudang' => $idPindahGudang])));
            }
            $tools->append($tools->renderView(route(admin_get_route('produk-mutasi.detail'), ['idPindahGudang' => $idPindahGudang])));
            $tools->append($tools->renderList(route(admin_get_route('produk-mutasi.list'))));
        });
        $form->text('transaksi_no')->readonly()->withoutIcon()->value($data->transaksi_no);
        $form->datetime('tanggal')->readonly()->value($data->tanggal);
        $form->select('from_gudang', 'Dari Gudang')->readonly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->from_gudang);
        $form->select('to_gudang', 'Ke Gudang')->readonly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->to_gudang);
        $form->switch('is_valid', 'Sudah di-valid?')->states([
            'on'  => ['value' => 1, 'text' => 'Valid', 'color' => 'success'],
            'off' => ['value' => 0, 'text' => 'Belum', 'color' => 'warning'],
        ])->value($data->is_valid);
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableReset();

        $grid = new Grid(new Produk());
        $grid->filter(function (Filter $filter) {
            $filter->expand();
            $filter->disableIdFilter();
            $filter->ilike('nama', 'Produk');
        });
        $grid->model()->with(['produkVarian.produkVarianHarga', 'produkVarian.produkPersediaan' => function ($rel) {
            $rel->addSelect(['hargabeli_avg' => ProdukPersediaanDetail::select(DB::raw('(sum(hargabeli*coalesce(stok_in,0) - hargabeli*coalesce(stok_out,0))/nullif(sum(coalesce(stok_in,0))-sum(coalesce(stok_out,0)),0))::int'))
                ->whereColumn('toko_griyanaura.ms_produkpersediaandetail.id_persediaan', 'toko_griyanaura.ms_produkpersediaan.id_persediaan')
            ]);
            $rel->with('produkVarianHarga');
        }, 'produkVarian.pindahGudangDetail' => function ($q) use ($data) {
            $q->where('id_pindahgudang', $data->id_pindahgudang);
        }])->where('in_stok', true);
        $grid->column('nama', __('Nama'))->expand(function ($model) use ($data) {
            $produkVarian = $model->produkVarian->map(function ($varian) use ($data) {
                $key = $varian['kode_produkvarian'];
                // set harga modal 
                if ($varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->hargabeli_avg) {
                    // pakai harga rata rata jika ada
                    $dariGudangHargaModal = $varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->hargabeli_avg;
                } else {
                    if ($varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->produkVarianHarga?->hargabeli) {
                        // pakai harga modal dari default harga persediaan
                        $dariGudangHargaModal = $varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->produkVarianHarga?->hargabeli;
                    } else if ($varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_fromgudang)?->hargabeli) {
                        // pakai harga modal dari default harga gudang
                        $dariGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_fromgudang)?->hargabeli;
                    } else {
                        // pakai harga modal reguler
                        $dariGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                    }
                }

                if ($varian->produkPersediaan->firstWhere('id_gudang', $data->to_gudang)?->produkVarianHarga?->hargabeli) {
                    $keGudangHargaModal = $varian->produkPersediaan->firstWhere('id_gudang', $data->to_gudang)?->produkVarianHarga?->hargabeli;
                } else if ($varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_togudang)?->hargabeli) {
                    $keGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_togudang)?->hargabeli;
                } else {
                    $keGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                }

                $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                $stokDariGudang = $varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->stok ?: 0;
                $stokDariGudang = \number($stokDariGudang);
                $dariGudangHargaModal = $varian->pindahGudangDetail?->harga_modal_dari_gudang ?: $dariGudangHargaModal;
                $dariGudangHargaModal =
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <input form="pindah-gudang-{$key}" readonly name="harga_modal_dari_gudang" value="{$dariGudangHargaModal}" class="form-control hargamodal" style="width:100px">
                    </div>
                HTML;
                $jumlahPindah = \number($varian->pindahGudangDetail?->jumlah);
                $stokKeGudang = $varian->produkPersediaan->firstWhere('id_gudang', $data->to_gudang)?->stok ?: 0;
                $stokKeGudang = \number($stokKeGudang);
                $keGudangHargaModal = $varian->pindahGudangDetail?->harga_modal_ke_gudang ?: $keGudangHargaModal;
                $keGudangHargaModal =
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <input form="pindah-gudang-{$key}" name="harga_modal_ke_gudang" value="{$keGudangHargaModal}" class="form-control hargamodal" style="width:100px">
                    </div>
                HTML;
                $dariGudang = 
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <input form="pindah-gudang-{$key}" name="jumlah" class="form-control" style="width:100px" type="number" max="{$stokDariGudang}" value="{$jumlahPindah}">
                        <span class="stok-dari-gudang" style="width:50px" align="center">/ {$stokDariGudang}</span>
                    </div>
                HTML;
                $keGudang = 
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <span class="stok-ke-gudang" style="width:50px" align="center"> {$stokKeGudang}</span>
                    </div>
                HTML;
                $action = route(admin_get_route('produk-mutasi.store.detail'), ['idPindahGudang' => $data->id_pindahgudang]);
                return [
                    "<form method='POST' action='{$action}' id='pindah-gudang-{$key}'>
                        <input hidden name='_token' value='".csrf_token()."'>
                        <input hidden name='kode_produkvarian' value='{$key}'>
                        <input hidden name='id_pindahgudangdetail' value='{$varian->pindahGudangDetail?->id_pindahgudangdetail}'>
                    </form>",
                    $varian['kode_produkvarian'],
                    $varian['varian'],
                    $dariGudangHargaModal,
                    $dariGudang,
                    $keGudangHargaModal,
                    $keGudang
                    // (fmod($varian->produkPersediaan?->first()?->stok, 1) !== 0.00) ? $varian->produkPersediaan?->first()?->stok : (int)$varian->produkPersediaan?->first()?->stok
                ];
            });
            return new Table(['','SKU', 'Varian', "Harga modal (Gudang {$data->nama_fromgudang})","(From) Gudang {$data->nama_fromgudang}", "Harga modal (Gudang {$data->nama_togudang})", "(To) Gudang {$data->nama_togudang}"], $produkVarian->toArray());
        }, true);
        $grid->disableColumnSelector();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableCreateButton();
        $grid->disableExport();
        return array($form, $grid);
    }
    public function detailProdukMutasiForm($idPindahGudang) {
        $form = new Form(new PindahGudang);
        $data = $form->model()->where('id_pindahgudang', $idPindahGudang)->first();
        $form->tools(function (Tools $tools) use ($idPindahGudang, $data) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            if (!$data->is_valid) {
                $tools->append($tools->renderDelete(path: route(admin_get_route('produk-mutasi.delete'), ['idPindahGudang' => $idPindahGudang]), listPath: route(admin_get_route('produk-mutasi.list'))));
                $tools->append($tools->renderEdit(route(admin_get_route('produk-mutasi.create.detail'), ['idPindahGudang' => $idPindahGudang])));
            }
            $tools->append($tools->renderList(route(admin_get_route('produk-mutasi.list'))));
        });
        $form->text('transaksi_no')->withoutIcon()->readonly()->value($data->transaksi_no);
        $form->datetime('tanggal')->value($data->tanggal);
        $form->select('from_gudang', 'Dari Gudang')->readOnly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->from_gudang);
        $form->select('to_gudang', 'Ke Gudang')->readOnly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->to_gudang);
        $form->text('keterangan')->readonly()->setWidth(4);
        $form->textarea('catatan')->readonly();
        $form->tablehasmany('pindahGudangDetail', 'Produk', function (NestedForm $form) {
            $form->text('kode_produkvarian')->setLabelClass(['w-100'])->disable()->withoutIcon();
            $form->text('jumlah')->setLabelClass(['w-200px'])->disable()->withoutIcon();
        })->useTable()->disableCreate()->disableDelete()->value($data->pindahGudangDetail->toArray() ?: [['id_pindahgudangdetail' => 0]]);
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableSubmit();
        $form->disableReset();
        
        return $form;
    }
    public function listProdukMutasi(Content $content) {
        return $content
            ->title('Produk')
            ->description('List')
            ->body($this->listProdukMutasiGrid());
    }
    public function createProdukMutasi(Content $content) {
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->createProdukMutasiForm()->setAction(route(admin_get_route('produk-mutasi.store'))));
    }
    public function createProdukMutasiDetail($idPindahGudang, Content $content) {
        if (PindahGudang::where('id_pindahgudang', $idPindahGudang)->first()->is_valid) {
            return redirect()->route(admin_get_route('produk-mutasi.detail'), ['idPindahGudang' => $idPindahGudang]);
        }
        $action = route(admin_get_route('produk-mutasi.store.detail'), ['idPindahGudang' => $idPindahGudang]);
        $scriptDeferred = 
        <<<SCRIPT
            $('.hargamodal').inputmask({
                "alias":"currency",
                "radixPoint":".",
                "prefix":"",
                "removeMaskOnSubmit":true
            });
            $('input[form^="pindah-gudang"]').on('change', function () {
                const that = this;
                function getFormData(form){
                    var unindexed_array = form.serializeArray();
                    var indexed_array = {};
                    $.map(unindexed_array, function(n, i){
                        if (n['name'] == 'harga_modal_dari_gudang' || n['name'] == 'harga_modal_ke_gudang') {
                            indexed_array[n['name']] = n['value'].slice(0, -3).replace(/[.,]/g, '');
                        } else {
                            indexed_array[n['name']] = n['value'];
                        }
                    });
                    return indexed_array;
                }
                $.ajax({
                    url: '{$action}',
                    type: 'POST',
                    data: JSON.stringify(getFormData($(this.form))),
                    contentType: 'application/json',
                    success: function(response) {
                        console.log('Data berhasil dikirim:', response);
                        $.admin.toastr['success']('Berhasil pindah gudang!');
                        $(that).closest('tr').find('[name="id_pindahgudangdetail"]').val(response.id_pindahgudangdetail);
                    },
                    error: function(xhr, status, error) {
                        $.admin.toastr['warning']('Gagal pindah gudang!');
                        if (xhr.status === 400) {
                            console.log('Bad Request');
                        } else if (xhr.status === 404) {
                            console.log('Not Found');
                        } else if (xhr.status === 500) {
                            console.log('Internal Server Error');
                        }
                    }
                });
            })
        SCRIPT;
        Admin::script($scriptDeferred, true);
        return $content
            ->title('Produk')
            ->body($this->createProdukMutasiDetailFormGrid($idPindahGudang)[0])
            ->body($this->createProdukMutasiDetailFormGrid($idPindahGudang)[1]);
    }
    public function detailProdukMutasi($idPindahGudang, Content $content) {
        $style = 
        <<<STYLE
            #has-many-pindahGudangDetail .input-group {
                width: 100%;
            }
            .w-200px {
                max-width: 200px;
                min-width: 200px;
            }
        STYLE;
        Admin::style($style);
        return $content
            ->title('Produk')
            ->body($this->detailProdukMutasiForm($idPindahGudang));
    }
    
    public function storeProdukMutasi(Request $request) {
        try {
            $result = $this->pindahGudangService->storePindahGudang($request->all());
            admin_toastr('Sukses membuat transaksi pindah gudang');
            return redirect()->route(admin_get_route('produk-mutasi.create.detail'), ['idPindahGudang' => $result->id_pindahgudang]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function storePindahGudangDetail($idPindahGudang, Request $request) {
        try {
            $result = $this->pindahGudangService->storePindahGudangDetail($idPindahGudang, $request->all());
            return response()->json([
                'id_pindahgudangdetail' =>  $result?->id_pindahgudangdetail
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // dump($e);
            throw $e;
        }
    }
    public function deleteProdukMutasi($idPindahGudang, Request $request) {
        $this->pindahGudangService->deletePindahGudang($idPindahGudang);
        admin_toastr('Sukses hapus produk');
        return [
            'status' => true,
            'then' => ['action' => 'refresh', 'value' => true],
            'message' => 'Sukses hapus pindah gudang'
        ];
    }
    public function validateProdukMutasi($idPindahGudang, Request $request) {
        try {
            $result = $this->pindahGudangService->validatePindahGudang($idPindahGudang, $request->all());
            admin_toastr('Sukses validasi mutasi gudang');
            return redirect()->route(admin_get_route('produk-mutasi.detail'), ['idPindahGudang' => $idPindahGudang]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
