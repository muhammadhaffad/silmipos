<?php

namespace App\Admin\Controllers;

use App\Admin\Form\Form;
use App\Http\Controllers\Controller;
use App\Models\PindahGudang;
use App\Models\Produk;
use App\Models\ProdukVarian;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form\Field\Button;
use Encore\Admin\Form\Field\Text;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Table;
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
        $data = $form->model()->where('id_pindahgudang', $idPindahGudang)->join(DB::raw('(select nama as nama_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg'), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')->join(DB::raw('(select nama as nama_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2'), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang')->first();
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
        $grid->model()->with(['produkVarian', 'produkVarian' => function ($relation) {
            $relation->with(['produkPersediaan']);
        }])->where('in_stok', true);
        $grid->column('nama', __('Nama'))->expand(function ($model) use ($data) {
            $produkVarian = $model->produkVarian->map(function ($varian) use ($data) {
                $stokDariGudang = $varian->produkPersediaan->firstWhere('id_gudang', $data->from_gudang)?->stok ?: 0;
                $stokDariGudang = (fmod($stokDariGudang, 1) !== 0.00) ? $stokDariGudang : (int)$stokDariGudang;
                $stokKeGudang = $varian->produkPersediaan->firstWhere('id_gudang', $data->to_gudang)?->stok ?: 0;
                $stokKeGudang = (fmod($stokKeGudang, 1) !== 0.00) ? $stokKeGudang : (int)$stokKeGudang;
                $dariGudang = '<form pjax-container><div class="d-flex gap-2 align-items-center"><input class="form-control" style="width:100px"><input class="form-control" style="width:100px"><span style="width:50px" align="center">/ '.$stokDariGudang.'</span><button type="submit" class="btn btn-primary"><i class="fa fa-arrow-circle-right"></i></button></div></form>';
                return [
                    $varian['kode_produkvarian'],
                    $varian['varian'],
                    $dariGudang,
                    '<input class="form-control" style="width:100px">' . $stokKeGudang
                    // (fmod($varian->produkPersediaan?->first()?->stok, 1) !== 0.00) ? $varian->produkPersediaan?->first()?->stok : (int)$varian->produkPersediaan?->first()?->stok
                ];
            });
            return new Table(['SKU', 'Varian', "(From) Gudang {$data->nama_fromgudang}", "(To) Gudang {$data->nama_togudang}"], $produkVarian->toArray());
        }, true);
        $grid->disableColumnSelector();
        $grid->disableRowSelector();
        $grid->disableActions();
        $grid->disableCreateButton();
        $grid->disableExport();
        // $grid->column('Gudang ' . $data->nama_fromgudang)->display(function () use ($data) {
        //     $fromGudang =  $this->produkPersediaan->where('id_gudang', $data->from_gudang)->first();
        //     if ($fromGudang)
        //         return (fmod($fromGudang['stok'], 1) !== 0.00) ? $fromGudang['stok'] : (int)$fromGudang['stok'];
        //     else
        //         return 0;
        // });
        // $grid->column('Gudang ' . $data->nama_togudang)->display(function () use ($data) {
        //     $toGudang =  $this->produkPersediaan->where('id_gudang', $data->to_gudang)->first();
        //     if ($toGudang) 
        //         return (fmod($toGudang['stok'], 1) !== 0.00) ? $toGudang['stok'] : (int)$toGudang['stok'];
        //     else
        //         return 0;
        // });
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
}
