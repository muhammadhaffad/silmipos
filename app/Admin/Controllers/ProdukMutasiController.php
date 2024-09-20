<?php

namespace App\Admin\Controllers;

use App\Admin\Form\Form;
use App\Http\Controllers\Controller;
use App\Models\PindahGudang;
use App\Models\ProdukVarian;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Illuminate\Support\Facades\DB;

class ProdukMutasiController extends Controller
{
    public function createProdukMutasiForm() {
        $form = new Form(new PindahGudang);
        $form->datetime('tanggal')->default(date('Y-m-d H:i:s'));
        $form->select('from_gudang', 'Dari Gudang')->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4);
        $form->select('to_gudang', 'Ke Gudang')->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4);
        $form->text('keterangan')->setWidth(4);
        $form->textarea('catatan');
        return $form;
    }
    public function createProdukMutasiDetailGrid($idPindahGudang) {
        $form = new Form(new PindahGudang);
        $data = $form->model()->where('id_pindahgudang', $idPindahGudang)->join(DB::raw('(select id_produk, in_stok from toko_griyanaura.ms_produk) as prd'), 'prd.id_produk', 'toko_griyanaura.ms_produkvarian.id_produk')->join(DB::raw('(select nama as nama_fromgudang, id_gudang from toko_griyanaura.lv_gudang) as gdg'), 'gdg.id_gudang', 'toko_griyanaura.tr_pindahgudang.from_gudang')->join(DB::raw('(select nama as nama_togudang, id_gudang from toko_griyanaura.lv_gudang) as gdg2'), 'gdg2.id_gudang', 'toko_griyanaura.tr_pindahgudang.to_gudang')->where('in_stok', true)->first();
        $form->tools(function (Tools $tools) {
            $tools->disableList();
            $tools->append($tools->renderList(''));
        });
        $form->datetime('tanggal')->readonly()->value($data->tanggal);
        $form->select('from_gudang', 'Dari Gudang')->readonly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->from_gudang);
        $form->select('to_gudang', 'Ke Gudang')->readonly()->options(DB::table('toko_griyanaura.lv_gudang')->select('nama as text', 'id_gudang as id')->get()->pluck('text', 'id'))->setWidth(4)->value($data->to_gudang);
        $form->disableCreatingCheck();
        $form->disableEditingCheck();
        $form->disableViewCheck();
        $form->disableSubmit();
        $form->disableReset();

        $grid = new Grid(new ProdukVarian);
        $grid->model()->with('produkPersediaan');
        $grid->column('kode_produkvarian');
        $grid->column('Gudang ' . $data->nama_fromgudang)->display(function () use ($data) {
            $fromGudang =  $this->produkPersediaan->where('id_gudang', $data->from_gudang)->first();
            return $fromGudang['stok'] ?? 0;
        });
        $grid->column('Gudang ' . $data->nama_togudang)->display(function () use ($data) {
            $toGudang =  $this->produkPersediaan->where('id_gudang', $data->to_gudang)->first();
            return $toGudang['stok'] ?? 0;
        });
        return array($form, $grid);
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
}
