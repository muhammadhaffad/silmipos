<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dynamic;
use App\Models\PenyesuaianGudang;
use App\Models\Produk;
use App\Services\Core\PenyesuaianGudang\PenyesuaianGudangService;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Filter;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdukPenyesuaianController extends Controller
{
    protected $penyesuaianGudangService;
    public function __construct(PenyesuaianGudangService $penyesuaianGudangService)
    {
        $this->penyesuaianGudangService = $penyesuaianGudangService;
    }
    public function createProdukPenyesuaianForm() {
        $form = new Form(new PenyesuaianGudang);
        $form->text('transaksi_no')->withoutIcon()->placeholder('[AUTO]');
        $form->datetime('tanggal')->default(date('Y-m-d H:i:s'));
        $form->text('keterangan')->setWidth(4);
        $form->textarea('catatan');
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        return $form;
    }
    public function createProdukPenyesuaianDetailFormGrid($idPenyesuaianGudang) {
        $form = new Form(new PenyesuaianGudang);
        $form->builder()->setMode('edit');
        $data = $form->model()->where('id_penyesuaiangudang', $idPenyesuaianGudang)->first();
        $form->tools(function (Tools $tools) use ($idPenyesuaianGudang) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('produk-mutasi.delete'), ['idPindahGudang' => $idPenyesuaianGudang])));
            $tools->append($tools->renderView(route(admin_get_route('produk-mutasi.detail'), ['idPindahGudang' => $idPenyesuaianGudang])));
            $tools->append($tools->renderList(route(admin_get_route('produk-mutasi.list'))));
        });
        $form->text('transaksi_no')->readonly()->withoutIcon()->value($data->transaksi_no);
        $form->datetime('tanggal')->readonly()->value($data->tanggal);
        $form->text('keterangan')->readonly()->withoutIcon()->value($data->keterangan);
        $form->textarea('catatan')->readonly()->value($data->catatan);
        $form->switch('is_valid', 'Sudah di-valid?')->states([
            'on'  => ['value' => 1, 'text' => 'Valid', 'color' => 'success'],
            'off' => ['value' => 0, 'text' => 'Belum', 'color' => 'warning'],
        ])->value($data->is_valid);
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableReset();

        $gudang = (new Dynamic())->setTable('toko_griyanaura.lv_gudang')->get()->toArray();
        $grid = new Grid(new Produk());
        $grid->filter(function (Filter $filter) {
            $filter->expand();
            $filter->disableIdFilter();
            $filter->ilike('nama', 'Produk');
        });
        $grid->model()->with(['produkVarian.produkVarianHarga', 'produkVarian.produkPersediaan' => function ($relation) use ($data) {
            $relation->leftJoin(DB::raw("(select id_penyesuaiangudangdetail, id_penyesuaiangudang, kode_produkvarian as kode_produkvarianpenyesuaian, jumlah as jumlah_penyesuaian, selisih as selisih_stokfisik, harga_modal, id_gudang as id_gudangpenyesuaian from toko_griyanaura.tr_penyesuaiangudangdetail) as pnyd"), function ($join) use ($data) {
                $join->on('pnyd.kode_produkvarianpenyesuaian', 'toko_griyanaura.ms_produkpersediaan.kode_produkvarian')
                    ->on('pnyd.id_gudangpenyesuaian', 'toko_griyanaura.ms_produkpersediaan.id_gudang')
                    ->on('pnyd.id_penyesuaiangudang', DB::raw($data->id_penyesuaiangudang));
            });
            $relation->with('produkVarianHarga');
        }])
            ->where('in_stok', true);
        $grid->column('nama', __('Nama'))->expand(function ($model) use ($gudang, $data) {
            $produkVarian = $model->produkVarian->map(function ($varian) use ($gudang, $data) {
                $key = $varian['kode_produkvarian'];
                $dataGudang = array();
                foreach ($gudang as $gdg) {
                    // set harga modal
                    if ($varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->harga_modal) {
                        $dataGudang[$gdg['id_gudang']]['hargamodal'] = $varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->harga_modal;
                    } else if ($varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->produkVarianHarga?->hargabeli) {
                        $dataGudang[$gdg['id_gudang']]['hargamodal'] = $varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->produkVarianHarga?->hargabeli;
                    } else if ($varian->produkVarianHarga->firstWhere('id_varianharga', $gdg['default_varianharga'])?->hargabeli) {
                        $dataGudang[$gdg['id_gudang']]['hargamodal'] = $varian->produkVarianHarga->firstWhere('id_varianharga', $gdg['default_varianharga'])?->hargabeli;
                    } else {
                        $dataGudang[$gdg['id_gudang']]['hargamodal'] = $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                    }
                    $stok = $varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->stok ?: 0;
                    $jumlahPenyesuaian = $varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->jumlah_penyesuaian;
                    $selisihStokFisik = $varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->selisih_stokfisik;
                    $dataGudang[$gdg['id_gudang']]['stok_persediaan'] = (fmod($stok, 1) !== 0.00) ? $stok : (int)$stok;
                    $dataGudang[$gdg['id_gudang']]['jumlah_penyesuaian'] = $jumlahPenyesuaian;
                    $dataGudang[$gdg['id_gudang']]['selisih_stokfisik'] = $selisihStokFisik;
                    $dataGudang[$gdg['id_gudang']]['id_gudang'] = $gdg['id_gudang'];
                    $dataGudang[$gdg['id_gudang']]['kode_produkvarian'] = $varian['kode_produkvarian'];
                    $dataGudang[$gdg['id_gudang']]['id_penyesuaiangudang'] = $data->id_penyesuaiangudang;
                    $dataGudang[$gdg['id_gudang']]['id_penyesuaiangudangdetail'] = $varian->produkPersediaan->firstWhere('id_gudang', $gdg['id_gudang'])?->id_penyesuaiangudangdetail;
                    $token = csrf_field();
                    $dataGudang[$gdg['id_gudang']]['html'] = $input = <<<HTML
                        <form action="">
                            <div class="d-flex gap-1">
                                $token
                                <input class="" name="id_gudang" hidden value="{$dataGudang[$gdg['id_gudang']]['id_gudang']}">
                                <input class="" name="id_penyesuaiangudang" hidden value="{$dataGudang[$gdg['id_gudang']]['id_penyesuaiangudang']}">
                                <input class="" name="id_penyesuaiangudangdetail" hidden value="{$dataGudang[$gdg['id_gudang']]['id_penyesuaiangudangdetail']}">
                                <input class="form-control" style="width: 80px" name="hargamodal" value="{$dataGudang[$gdg['id_gudang']]['hargamodal']}">
                                <input class="form-control" style="width: 50px" readonly value="{$dataGudang[$gdg['id_gudang']]['stok_persediaan']}">
                                <input class="form-control penyesuaian-stok" style="width: 50px" name="jumlah_penyesuaian" value="{$dataGudang[$gdg['id_gudang']]['jumlah_penyesuaian']}" placeholder="{$dataGudang[$gdg['id_gudang']]['stok_persediaan']}">
                                <!-- <button class="btn btn-primary"><span class="fa fa-save"></span></button> -->
                            </div>
                        </form>
                    HTML;
                }
                $action = route(admin_get_route('produk-mutasi.store.detail'), ['idPindahGudang' => $data->id_penyesuaiangudang]);
                $tbody = [
                    "<form method='POST' action='{$action}' id='pindah-gudang-{$key}'>
                        <input hidden name='_token' value='".csrf_token()."'>
                        <input hidden name='kode_produkvarian' value='{$key}'>
                    </form>",
                    $varian['kode_produkvarian'],
                    $varian['varian']
                    // (fmod($varian->produkPersediaan?->first()?->stok, 1) !== 0.00) ? $varian->produkPersediaan?->first()?->stok : (int)$varian->produkPersediaan?->first()?->stok
                ];
                foreach ($gudang as $gdg) {
                    $tbody[] = $dataGudang[$gdg['id_gudang']]['html'];
                }
                return $tbody;
            });
            $thead = ['','SKU','Varian'];
            foreach ($gudang as $gdg) {
                $thead[] = 'Gudang ' . $gdg['nama'] . ' (Modal/Aplikasi/Fisik)';
            }
            return new Table($thead, $produkVarian->toArray());
        }, true);
        $grid->disableColumnSelector();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableCreateButton();
        $grid->disableExport();
        return array($form, $grid);
    }
    public function createProdukPenyesuaian(Content $content) {
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->createProdukPenyesuaianForm()->setAction(route(admin_get_route('produk-penyesuaian.store'))));
    }
    public function createProdukPenyesuaianDetail($idPenyesuaianGudang, Content $content) {
        $action = route(admin_get_route('produk-mutasi.store.detail'), ['idPindahGudang' => $idPenyesuaianGudang]);
        $scriptDeferred = 
        <<<SCRIPT
            $('.penyesuaian-stok').on('change', function () {
                console.info($(this.form).serialize());
            });            
        SCRIPT;
        Admin::script($scriptDeferred, true);
        return $content
            ->title('Produk')
            ->body($this->createProdukPenyesuaianDetailFormGrid($idPenyesuaianGudang)[0])
            ->body($this->createProdukPenyesuaianDetailFormGrid($idPenyesuaianGudang)[1]);
    }
    public function storeProdukPenyesuaian(Request $request) {
        try {
            $result = $this->penyesuaianGudangService->storePenyesuaianGudang($request->all());
            admin_toastr('Sukses membuat transaksi penyesuaian gudang');
            return redirect()->route(admin_get_route('produk-penyesuaian.create.detail'), ['idPenyesuaianGudang' => $result->id_penyesuaiangudang]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }
    public function storeProdukPenyesuaianDetail(Request $request) {
        try {
            $this->penyesuaianGudangService->storePenyesuaianGudangDetail($request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
