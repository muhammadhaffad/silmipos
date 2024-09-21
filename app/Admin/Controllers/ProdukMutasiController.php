<?php

namespace App\Admin\Controllers;

use App\Admin\Form\Form;
use App\Http\Controllers\Controller;
use App\Models\PindahGudang;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Encore\Admin\Admin;
use Encore\Admin\Form\Field\Button;
use Encore\Admin\Form\Field\Text;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdukMutasiController extends Controller
{
    public function createProdukMutasiForm() {
        $form = new Form(new PindahGudang);
        $form->text('transaksi_no')->withoutIcon()->placeholder('[AUTO]');
        $form->datetime('tanggal')->default(date('Y-m-d H:i:s'));
        $form->select('from_gudang', 'Dari Gudang')->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4);
        $form->select('to_gudang', 'Ke Gudang')->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4);
        $form->text('keterangan')->setWidth(4);
        $form->textarea('catatan');
        return $form;
    }
    public function createProdukMutasiDetailGrid($idPindahGudang) {
        $form = new Form(new PindahGudang);
        $data = $form->model()->where('id_pindahgudang', $idPindahGudang)
            ->join(DB::raw('(select nama as nama_fromgudang, default_varianharga as varianharga_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg'), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')
            ->join(DB::raw('(select nama as nama_togudang, default_varianharga as varianharga_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2'), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang')
            ->first();
        $form->tools(function (Tools $tools) {
            $tools->disableList();
            $tools->append($tools->renderList(''));
        });
        $form->text('transaksi_no')->readonly()->withoutIcon()->value($data->transaksi_no);
        $form->datetime('tanggal')->readonly()->value($data->tanggal);
        $form->select('from_gudang', 'Dari Gudang')->readonly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->from_gudang);
        $form->select('to_gudang', 'Ke Gudang')->readonly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->to_gudang);
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableSubmit();
        $form->disableReset();

        $grid = new Grid(new Produk());
        $grid->model()->with(['produkVarian.produkVarianHarga', 'produkVarian.produkPersediaan.produkVarianHarga'])
            ->where('in_stok', true);
        $grid->column('nama', __('Nama'))->expand(function ($model) use ($data) {
            $produkVarian = $model->produkVarian->map(function ($varian) use ($data) {
                $key = $varian['kode_produkvarian'];
                // set harga modal 
                if ($varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->produkVarianHarga?->hargabeli) {
                    $dariGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_fromgudang)?->hargabeli;
                } else if ($varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_fromgudang)?->hargabeli) {
                    $dariGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_fromgudang)?->hargabeli;
                } else {
                    $dariGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                }

                if ($varian->produkPersediaan->firstWhere('id_gudang', $data->to_gudang)?->produkVarianHarga?->hargabeli) {
                    $keGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_togudang)?->hargabeli;
                } else if ($varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_togudang)?->hargabeli) {
                    $keGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', $data->varianharga_togudang)?->hargabeli;
                } else {
                    $keGudangHargaModal = $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                }

                $varian->produkVarianHarga->firstWhere('id_varianharga', 1)->hargabeli;
                $stokDariGudang = $varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->stok ?: 0;
                $stokDariGudang = (fmod($stokDariGudang, 1) !== 0.00) ? $stokDariGudang : (int)$stokDariGudang;
                $stokKeGudang = $varian->produkPersediaan->firstWhere('id_gudang', $data->to_gudang)?->stok ?: 0;
                $stokKeGudang = (fmod($stokKeGudang, 1) !== 0.00) ? $stokKeGudang : (int)$stokKeGudang;
                $dariGudangHargaModal =
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <input form="pindah-gudang-{$key}" name="harga_modal_dari_gudang" value="{$dariGudangHargaModal}" class="form-control hargamodal" style="width:100px">
                    </div>
                HTML;
                $keGudangHargaModal =
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <input form="pindah-gudang-{$key}" name="harga_modal_ke_gudang" value="{$keGudangHargaModal}" class="form-control hargamodal" style="width:100px">
                    </div>
                HTML;
                $dariGudang = 
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <input form="pindah-gudang-{$key}" name="jumlah" class="form-control" style="width:100px" type="number" max="{$stokDariGudang}" value="{$varian['jumlah_pindah']}">
                        <span style="width:50px" align="center">/ {$stokDariGudang}</span>
                        <button form="pindah-gudang-{$key}" type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i>
                        </button>
                    </div>
                HTML;
                $keGudang = 
                <<<HTML
                    <div class="d-flex gap-2 align-items-center">
                        <span style="width:50px" align="center"> {$stokKeGudang}</span>
                    </div>
                HTML;
                return [
                    "<form method='POST' id='pindah-gudang-{$key}'>
                        <input hidden name='_token' value='".csrf_token()."'>
                        <input hidden name='kode_produkvarian' value='{$key}'>
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
    public function createProdukMutasi(Content $content) {
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->createProdukMutasiForm());
    }
    public function createProdukMutasiDetail($idPindahGudang, Content $content) {
        $scriptDeferred = 
        <<<SCRIPT
            $('.hargamodal').inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
        SCRIPT;
        Admin::script($scriptDeferred, true);
        return $content
            ->title('Produk')
            ->body($this->createProdukMutasiDetailGrid($idPindahGudang)[0])
            ->body($this->createProdukMutasiDetailGrid($idPindahGudang)[1]);
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
    public function createOrUpdatePindahGudangDetail(Request $request) {

    }
}
