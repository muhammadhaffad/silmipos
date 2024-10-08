<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\Delete;
use App\Admin\Actions\Grid\Edit;
use App\Admin\Actions\Grid\Show;
use App\Admin\Forms\Produk\AkuntingProduk;
use App\Admin\Forms\Produk\InformasiProduk;
use App\Http\Controllers\Controller;
use App\Models\Dynamic;
use App\Models\Produk;
use App\Services\Core\Produk\ProdukService;
use Encore\Admin\Admin;
// use App\Admin\Form\Form;
use Encore\Admin\Form;
use Encore\Admin\Form\Footer;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Column\Sorter;
use Encore\Admin\Grid\Displayers\Actions;
use Encore\Admin\Grid\Displayers\DropdownActions;
use Encore\Admin\Grid\Filter;
use Encore\Admin\Grid\Filter\Group;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Widgets\Form as WidgetsForm;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use function Termwind\style;

class ProdukController extends Controller
{
    protected $produkService;
    public function __construct(ProdukService $produkService)
    {
        $this->produkService = $produkService;
    }
    protected function listProdukGrid()
    {
        $grid = new Grid(new Produk());
        $grid->model()->with(['produkVarian' => function ($relation) {
            $relation->with(['produkVarianHarga' => function ($query) {
                $query->where('id_varianharga', 1);
            }]);
        }, 'produkVarian' => function ($relation) {
            $relation->with(['produkPersediaan' => function ($query) {
                $query->where('gdg.id_gudang', 1);
            }]);
        }]);
        $grid->model()->join(DB::raw('(select kode_unit, nama as namaunit from toko_griyanaura.lv_unit) as u'), 'toko_griyanaura.ms_produk.default_unit', 'u.kode_unit');
        if (!request()->get('_sort') and !request()->get('_customSort')) {
            $grid->model()->orderByDesc('toko_griyanaura.ms_produk.inserted_at');
        }
        if (request()->get('produk')) {
            $grid->model()->where('nama', 'ilike', "%" . request()->get('produk') . "%");
        }
        if (isset(request()->get('varian')[0]) and !empty(request()->get('varian')[0])) {
            $grid->model()->whereHas('produkVarian', function ($query) {
                $query->leftJoin('toko_griyanaura.ms_produkattributvarian as pav', 'pav.kode_produkvarian', 'toko_griyanaura.ms_produkvarian.kode_produkvarian')
                    ->whereIn('pav.id_attributvalue', request()->get('varian'));
            });
        }
        if (@request()->get('_customSort')['column'] == 'nama') {
            if (@request()->get('_customSort')['type'] == 'asc') {
                $grid->model()->orderByRaw('nama asc');
            } else if (@request()->get('_customSort')['type'] == 'desc') {
                $grid->model()->orderByRaw('nama desc');
            }
        }
        if (@request()->get('_customSort')['column'] == 'namaunit') {
            if (@request()->get('_customSort')['type'] == 'asc') {
                $grid->model()->orderByRaw('namaunit asc');
            } else if (@request()->get('_customSort')['type'] == 'desc') {
                $grid->model()->orderByRaw('namaunit desc');
            }
        }
        if (@request()->get('_customSort')['column'] == 'hargajual') {
            $grid->model()
                ->leftJoin(DB::raw("(select pv.id_produk, min(pvh.hargajual) as minhargajual, max(pvh.hargajual) as maxhargajual from toko_griyanaura.ms_produkvarian pv join toko_griyanaura.ms_produkvarianharga pvh using (kode_produkvarian) join toko_griyanaura.ms_produkharga ph using (id_produkharga) where ph.id_varianharga = 1 group by pv.id_produk) as pv"), 'toko_griyanaura.ms_produk.id_produk', 'pv.id_produk');
            if (@request()->get('_customSort')['type'] == 'asc') {
                $grid->model()->orderByRaw('minhargajual asc');
            } else if (@request()->get('_customSort')['type'] == 'desc') {
                $grid->model()->orderByRaw('minhargajual desc');
            }
        }
        if (@request()->get('_customSort')['column'] == 'totalvarian') {
            $grid->model()
                ->leftJoin(DB::raw('(select id_produk, count(kode_produkvarian) as totalvarian from toko_griyanaura.ms_produkvarian group by id_produk) as pv'), 'toko_griyanaura.ms_produk.id_produk', 'pv.id_produk');
            if (@request()->get('_customSort')['type'] == 'asc') {
                $grid->model()->orderByRaw('pv.totalvarian asc');
            } else if (@request()->get('_customSort')['type'] == 'desc') {
                $grid->model()->orderByRaw('pv.totalvarian desc');
            }
        }
        if (@request()->get('_customSort')['column'] == 'totalstok') {
            $grid->model()
                ->leftJoin(DB::raw('(select pv.id_produk, sum(pp.stok) as totalstok from toko_griyanaura.ms_produkvarian pv join toko_griyanaura.ms_produkpersediaan pp using (kode_produkvarian) group by id_produk) as pv'), 'toko_griyanaura.ms_produk.id_produk', 'pv.id_produk');
            if (@request()->get('_customSort')['type'] == 'asc') {
                $grid->model()->orderByRaw('pv.totalstok asc');
            } else if (@request()->get('_customSort')['type'] == 'desc') {
                $grid->model()->orderByRaw('pv.totalstok desc');
            }
        }

        $grid->filter(function (Filter $filter) {
            $filter->expand();
            $filter->disableIdFilter();
            $filter->column(1 / 2, function (Filter $filter) {
                $filter->where(function () {}, 'Produk', 'produk');
                $filter->where(function () {}, 'Varian', 'varian')->multipleSelect()->default('*')->setResource(route(admin_get_route('ajax.varians')))->config('allowClear', false)->ajax(route(admin_get_route('ajax.varians')));
            });
            $filter->column(1 / 2, function (Filter $filter) {
                $filter->where(function ($query) {
                    if ($this->input == '1') {
                        $query->where('in_stok', DB::raw('true'));
                    } else if ($this->input == '2') {
                        $query->where('in_stok', false);
                    }
                }, 'Tipe produk', 'tipe')->select([
                    '*' => 'Semua',
                    '1' => 'Inventori',
                    '2' => 'Non inventori'
                ])->default('*');
            });
        });

        $grid->column('nama', __('Nama'))->expand(function ($model) {
            $produkVarian = $model->produkVarian->map(function ($varian) {
                return [
                    $varian['kode_produkvarian'],
                    $varian['varian'],
                    'Rp ' . number_format($varian->produkVarianHarga->first()->hargajual),
                    (fmod($varian->produkPersediaan?->first()?->stok, 1) !== 0.00) ? $varian->produkPersediaan?->first()?->stok : (int)$varian->produkPersediaan?->first()?->stok
                ];
            });
            return new Table(['SKU', 'Varian', 'Harga jual', 'Stok'], $produkVarian->toArray());
        })->addHeader(new Sorter('_customSort', 'nama', null));
        $grid->column('produkAttribut', 'Attribut')->display(function ($value) {
            $varian = [];
            foreach ($value as $attr) {
                $varian[] = '<b>' . $attr['nama'] . '</b> : ' . $attr['varian'];
            }
            return implode("&nbsp;&nbsp;&nbsp;", $varian);
        });
        $grid->column('namaunit', 'Unit')->addHeader(new Sorter('_customSort', 'namaunit', null));
        $grid->column('hargajual', 'Harga jual')->display(function () {
            $min = min(array_column($this['produkVarian']->map(function ($item) {
                return @$item->produkVarianHarga->where('id_varianharga', 1)->first();
            })->toArray(), 'hargajual') ?: [0]);
            $max = max(array_column($this['produkVarian']->map(function ($item) {
                return @$item->produkVarianHarga->where('id_varianharga', 1)->first();
            })->toArray(), 'hargajual') ?: [0]);
            return 'Rp ' . number_format($min) . ' s/d ' . 'Rp ' . number_format($max);
        })->addHeader(new Sorter('_customSort', 'hargajual', null));
        $grid->column('totalvarian', 'Total varian')->display(function () {
            $count = count($this['produkVarian']->toArray());
            return "<span class='label label-primary'>{$count} varian</span>";
        })->addHeader(new Sorter('_customSort', 'totalvarian', null));
        $grid->column('totalstok', 'Total stok')->display(function () {
            $totalStok = array_sum($this['produkVarian']->map(function($item) {return $item->produkPersediaan->sum('stok');})->toArray());
            return "<span class='label label-warning'>{$totalStok}</span>";
        })->addHeader(new Sorter('_customSort', 'totalstok', null));
        $grid->actions(function (DropdownActions $actions) {
            $actions->disableDelete();
            $actions->disableEdit();
            $actions->disableView();
            $actions->add(new Show);
            $actions->add(new Edit);
            // dump($this);
            $actions->add(new Delete(route(admin_get_route('produk.delete'), $this->row->id_produk)));
        });

        return $grid;
    }
    public function showProdukForm($id)
    {
        $form = new Form(new Produk);
        $data = $form->model()->with(['produkAttribut', 'produkVarian' => function ($relation) {
            $relation->with(['produkVarianHarga' => function ($query) {
                $query->join(DB::raw("(select id_varianharga, nama as namavarianharga from toko_griyanaura.lv_varianharga) as vh"), 'ph.id_varianharga', 'vh.id_varianharga');
            }, 'produkPersediaan']);
        }])->find($id);
        $form->builder()->setResourceId($id);
        $form->builder()->setTitle($data->nama)->setMode('edit');
        $form->tools(function (Tools $tools) use ($id) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('produk.delete'), ['id' => $id])));
            $tools->append($tools->renderEdit(route(admin_get_route('produk.edit'), ['id' => $id])));
            $tools->append($tools->renderList(route(admin_get_route('produk.list'))));
        });
        $form->footer(function (Footer $footer) {
            $footer->disableCreatingCheck();
            $footer->disableEditingCheck();
            $footer->disableViewCheck();
            $footer->disableReset();
            $footer->disableSubmit();
        });
        $form->tab('Produk', function (Form $form) use ($data) {
            $form->display('SKU', __('SKU'))->setWidth(2)->value(@$data->produkVarian[0]->kode_produkvarian);
            $form->display('nama', __('Nama produk'))->value($data->nama);
            $form->select('default_unit', 'Satuan')->setWidth(2)->options((new Dynamic)->setTable('toko_griyanaura.lv_unit')->select('kode_unit as id', 'nama as text')->pluck('text', 'id')->toArray())->disable()->value($data->default_unit);
            $form->display('deskripsi')->attribute('style', 'height:300px;overflow:auto;')->value($data->deskripsi);
            $form->display('hargajual', 'Harga Jual')->attribute('align', 'right')->value('Rp ' . number_format(@$data->produkVarian[0]->hargajual))->setWidth(2);
            $form->display('hargabeli', 'Harga Modal')->attribute('align', 'right')->value('Rp ' . number_format(@$data->produkVarian[0]->default_hargabeli))->setWidth(2);
            $form->switch('in_stok', 'Produk di-stok?')->disable()->states([
                'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->value($data->in_stok);
            $form->divider();
            $optionsVarian = array();
            foreach ($data->produkAttribut as $attribut) {
                $optionsVarian[$attribut['id_produkattribut']] = (new Dynamic)->setTable('toko_griyanaura.ms_produkattributvarian as pav')->join('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')->where('id_produkattribut', $attribut['id_produkattribut'])->select('av.id_attributvalue as id', 'av.nama as text')->pluck('text', 'id')->toArray();
            }
            // $varianOptions = $data->produkVarian->pluck('varian', 'varian_id')->toArray();
            $form->tablehasmany('produkVarian', 'Varian', function (NestedForm $form) use ($data, $optionsVarian) {
                $form->display('kode_produkvarian', 'SKU');
                $form->display('varian', 'Varian')->customFormat(function ($value) {
                    return $value ?: '--Tidak ada--';
                });
                $form->display('produk_varian_harga.0.hargajual', 'Harga Jual')->attribute('align', 'right')->customFormat(function ($value) {
                    return 'Rp ' . number_format($value);
                });
                $form->display('produk_varian_harga.0.hargabeli', 'Harga Modal')->attribute('align', 'right')->customFormat(function ($value) {
                    return 'Rp ' . number_format($value);
                });
                $form->display('minstok', 'Min. Stok')->customFormat(function ($value) {
                    return number_format($value);
                });
            })->disableCreate()->disableDelete()->value($data->produkVarian->toArray())->useTable();
        })->tab('Akunting', function (Form $form) use ($data) {
            $form->select('default_akunpersediaan', __('Akun persediaan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('1301')
                ->disable()
                ->value($data->default_akunpersediaan);

            $form->select('default_akunpemasukan', __('Akun pemasukan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('4001')
                ->disable()
                ->value($data->default_akunpemasukan);

            $form->select('default_akunbiaya', __('Akun Biaya'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('5002')
                ->disable()
                ->value($data->default_akunbiaya);
        })->tab('Harga', function (Form $form) use ($data) {
            $produkVarianWithHarga = [];
            foreach ($data->produkVarian->toArray() as $varian) {
                $kodeProdukVarian = $varian['kode_produkvarian'];
                foreach ($varian['produk_varian_harga'] as $k => $varianHarga) {
                    $varian['hargajual'] = $varianHarga['hargajual'];
                    $varian['hargabeli'] = $varianHarga['hargabeli'];
                    $varian['namavarianharga'] = $varianHarga['namavarianharga'];
                    $varian['kode_produkvarian_label'] = $kodeProdukVarian;
                    $varian['kode_produkvarian'] = $kodeProdukVarian . $k; // agar bisa duplikat
                    $produkVarianWithHarga[] = $varian;
                }
            }
            $form->tablehasmany('produkVarian', 'Varian', function (NestedForm $form) {
                $form->display('kode_produkvarian_label', 'SKU');
                $form->display('varian', 'Varian');  
                $form->display('namavarianharga', 'Jenis Harga');  
                $form->display('hargajual', 'Harga Jual')->attribute(['align' => 'right'])->customFormat(function ($x) {
                    return 'Rp' . number_format($x);
                });  
                $form->display('hargabeli', 'Harga Modal')->attribute(['align' => 'right'])->customFormat(function ($x) {
                    return 'Rp' . number_format($x);
                });
            })->disableCreate()->disableDelete()->value($produkVarianWithHarga)->useTable();
        })->tab('Persediaan', function (Form $form) use ($data) {
            $produkVarianPersediaan = [];
            foreach ($data->produkVarian->toArray() as $varian) {
                $kodeProdukVarian = $varian['kode_produkvarian'];
                foreach ($varian['produk_persediaan'] as $persediaan) {
                    $varian['stok'] = $persediaan['stok'];
                    $varian['nama_gudang'] = $persediaan['nama_gudang'];
                    $varian['kode_produkvarian_label'] = $kodeProdukVarian;
                    $varian['kode_produkvarian'] = $kodeProdukVarian . $persediaan['id_gudang']; // agar bisa duplikat
                    $produkVarianPersediaan[] = $varian;
                }
            }
            $form->tablehasmany('produkVarian', 'Varian', function (NestedForm $form) {
                $form->display('kode_produkvarian_label', 'SKU');
                $form->display('varian', 'Varian');  
                $form->display('nama_gudang', 'Gudang');  
                $form->display('stok', 'Stok');
            })->disableCreate()->disableDelete()->value($produkVarianPersediaan)->useTable();
        });
        
        return $form;
    }
    public function createProdukForm($request)
    {
        $form = new Form(new Produk);
        $form->builder()->setTitle('Tambah Produk')->setMode('create');
        $form->tab('Produk', function (Form $form) {
            $form->text('kode_produk', __('SKU'))->placeholder('[AUTO]')->withoutIcon()->setWidth(2);
            $form->text('nama', __('Nama produk'))->withoutIcon()->required();
            $form->select('default_unit', 'Satuan')->required()->setWidth(2)->options((new Dynamic)->setTable('toko_griyanaura.lv_unit')->select('kode_unit as id', 'nama as text')->pluck('text', 'id')->toArray());
            $form->ckeditor('deskripsi');
            $form->currency('hargajual', 'Harga jual')->symbol('Rp');
            $form->currency('default_hargabeli', 'Harga Modal')->symbol('Rp');
            $form->switch('in_stok', 'Produk di-stok?')->states([
                'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->default(true);
            $form->text('minstok', 'Min. stok')->attribute('type', 'number')->withoutIcon()->width('100px');
            $form->switch('has_varian', 'Produk memiliki varian?')->states([
                'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->default(false);
            $form->html('<div id="produk-varian-section" class="d-none">')->plain();
            $form->divider();
            $form->tablehasmany('produkAttribut', 'Varian Produk', function (NestedForm $form) {
                $form->select('id_attribut', 'Varian')->options((new Dynamic())->setTable('toko_griyanaura.lv_attribut')->select('id_attribut as id', 'nama as text')->pluck('text', 'id')->toArray());
                $form->multipleSelect('id_attributvalue', 'Nilai Varian');
            })->value([
                ['id_produkattribut' => '0']
            ])->useTable();
            $form->divider();
            $form->tablehasmany('produkVarian', '', function (NestedForm $form) {
                $keyName = 'kode_produkvarian_new';
                if ($form->model()) {
                    $key = $form->model()->getKey();
                } else {
                    $key = 'new___LA_KEY__';
                }
                $form->text('kode_produkvarian_new', 'SKU')->style('width', '150px')->placeholder('[AUTO]')->withoutIcon()->customFormat(function ($x) {
                    return $x;
                })->setElementName("produkVarian[{$key}][{$keyName}]");
                $form->html('<span id="varian">--Tidak Ada--</span>', 'Varian');
                $form->currency('hargajual', 'Harga Jual')->symbol('Rp');
                $form->currency('default_hargabeli', 'Harga Modal')->symbol('Rp');
                $form->text('stok', 'Stok')->attribute('type', 'number')->attribute('step', '.01')->style('min-width', '80px')->default(0)->withoutIcon()->disable();
                $form->text('minstok', 'Min Stok')->attribute('type', 'number')->attribute('step', '.01')->style('min-width', '80px')->default('0.00')->withoutIcon();
            })->disableDelete()->disableCreate()->value([
                [
                    'kode_produkvarian' => '0'
                ]
            ])->useTable();
            $form->html('</div>')->plain();
        })->tab('Akunting', function (Form $form) {
            $form->select('default_akunpersediaan', __('Akun persediaan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('1301')
            ;

            $form->select('default_akunpemasukan', __('Akun pemasukan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('4001')
            ;

            $form->select('default_akunbiaya', __('Akun Biaya'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('5002')
            ;
        });
        return $form;
    }
    public function editProdukForm($id, $request)
    {
        $form = new Form(new Produk);
        $data = $form->model()->with(['produkAttribut', 'produkVarian' => function ($relation) {
            $relation->with(['produkVarianHarga' => function ($q) {
                $q->where('id_varianharga', '1');
            }]);
        }])->find($id);
        $form->tools(function (Tools $tools) use ($id) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('produk.delete'), ['id' => $id])));
            $tools->append($tools->renderEdit(route(admin_get_route('produk.edit.harga'), ['id' => $id]), 'Edit Harga'));
            $tools->append($tools->renderView(route(admin_get_route('produk.detail'), ['id' => $id])));
            $tools->append($tools->renderList(route(admin_get_route('produk.list'))));
        });
        $form->builder()->setResourceId($id);
        $form->builder()->setTitle($data->nama)->setMode('edit');
        $form->tab('Produk', function (Form $form) use ($data) {
            $form->text('nama', __('Nama produk'))->rules('required|min:5')->withoutIcon()->required()->value($data->nama);
            $form->select('default_unit', 'Satuan')->required()->setWidth(4)->options((new Dynamic)->setTable('toko_griyanaura.lv_unit')->select('kode_unit as id', 'nama as text')->pluck('text', 'id')->toArray())->value($data->default_unit);
            $form->ckeditor('deskripsi')->value($data->deskripsi);
            $form->switch('in_stok', 'Produk di-stok?')->disable()->states([
                'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->value($data->in_stok);
            $form->divider();
            $optionsVarian = array();
            foreach ($data->produkAttribut as $attribut) {
                $optionsVarian[$attribut['id_produkattribut']] = (new Dynamic)->setTable('toko_griyanaura.ms_produkattributvarian as pav')->join('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')->where('id_produkattribut', $attribut['id_produkattribut'])->select('av.id_attributvalue as id', 'av.nama as text')->pluck('text', 'id')->toArray();
            }
            $attributs = (new Dynamic())->setTable('toko_griyanaura.lv_attribut as att')->select('id_attribut as id', 'nama as text')->pluck('text', 'id')->toArray();
            $form->tablehasmany('produkAttribut', 'Varian', function (NestedForm $form) use ($attributs) {
                $form->select('id_attribut', 'Attribut')
                    ->attribute('data-index', $form->model()?->index)
                    ->required()
                    ->options($attributs);
            })->value($data->produkAttribut->map(fn ($item, $index) => array_merge($item->toArray(), ['index' => $index]))->toArray())
                ->useTable();
            $form->tablehasmany('produkVarian', 'Produk varian', function (NestedForm $form) use ($data, $optionsVarian) {
                $keyName = 'kode_produkvarian_new';
                if ($form->model()) {
                    $row = $form->model();
                    $key = $form->getKey();
                } else {
                    $key = 'new___LA_KEY__';
                }
                $attributVarian = array_replace(...json_decode($row->varian_id ?? '[{}]', true));
                if (isset($row)) {
                    /* Jika ada row, maka sama dengan data disable (tidak bisa diubah), otomatis select pakai options */
                    $form->text('kode_produkvarian')
                        ->withoutIcon()
                        ->customFormat(function ($x) {
                            return $x;
                        })
                        ->setElementName("produkVarian[{$key}][{$keyName}]");
                    foreach ($data->produkAttribut->toArray() as $key => $attr) {
                        $form->select($attr['id_produkattribut'], $attr['nama'])
                            ->placeholder('')
                            ->setScript('')
                            ->required()
                            ->attribute([
                                'varian' => null, 
                                'select2' => null, 
                                'data-kode-produkvarian' => $row->kode_produkvarian,
                                'data-url' => route(admin_get_route('ajax.attribut-value')),
                                'data-index-varian' => $key
                            ])
                            ->default($attributVarian[$attr['id_produkattribut']] ?? '')
                            ->options($optionsVarian[$attr['id_produkattribut']])
                            ->customFormat(function ($x) {
                                return $x;
                            })
                            ->ajax(route(admin_get_route('ajax.attribut-value')));
                    }
                } else {
                    /* Jika tidak ada row, maka sama dengan tambah produk, otomatis select pakai ajax */
                    $form->text('kode_produkvarian')
                        ->withoutIcon()
                        ->required()
                        ->setElementName("produkVarian[{$key}][{$keyName}]");
                    foreach ($data->produkAttribut->toArray() as $key => $attr) {
                        $form->select($attr['id_produkattribut'], $attr['nama'])
                            ->setLabelClass(['varian index-varian-' . $key])
                            ->placeholder('')
                            ->attribute([
                                'varian' => null, 
                                'select2' => null,
                                'data-index-varian' => $key,
                                'data-kode-produkvarian' => 'new___LA_KEY__',
                                'data-url' => route(admin_get_route('ajax.attribut-value'))
                            ])
                            ->ajax(route(admin_get_route('ajax.attribut-value') ));
                    }
                }
                $form->currency('produk_varian_harga.0.hargajual', 'Harga Jual')->removeElementClass('produk_varian_harga.0.hargajual')->addElementClass('hargajual')->symbol('Rp');
                $form->currency('produk_varian_harga.0.hargabeli', 'Harga Modal')->removeElementClass('produk_varian_harga.0.hargabeli')->addElementClass('hargabeli')->symbol('Rp');
                if ($data->in_stok) {
                    $form->text('minstok', 'Min Stok')->attribute('type', 'number')->attribute('step', '.01')->style('min-width', '80px')->default('0.00')->withoutIcon();
                }
            })->value($data->produkVarian->toArray())->useTable();
        })->tab('Akunting', function (Form $form) use ($data) {
            $form->select('default_akunpersediaan', __('Akun persediaan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->attribute('akun')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('1301')
                ->value($data->default_akunpersediaan);

            $form->select('default_akunpemasukan', __('Akun pemasukan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->attribute('akun')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('4001')
                ->value($data->default_akunpemasukan);

            $form->select('default_akunbiaya', __('Akun Biaya'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->attribute('akun')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('5002')
                ->value($data->default_akunbiaya);
        });
        return $form;
    }

    public function editHargaProdukForm($id, $request) {
        $form = new Form(new Produk);
        $data = $form->model()->with(['produkAttribut', 'produkHarga', 'produkVarian.produkVarianHarga'])->find($id);
        $form->tools(function (Tools $tools) use ($id) {
            $tools->disableList();
            $tools->disableView();
            $tools->disableDelete();
            $tools->append($tools->renderDelete(route(admin_get_route('produk.delete'), ['id' => $id])));
            $tools->append($tools->renderEdit(route(admin_get_route('produk.edit'), ['id' => $id]), 'Edit Produk'));
            $tools->append($tools->renderView(route(admin_get_route('produk.detail'), ['id' => $id])));
            $tools->append($tools->renderList(route(admin_get_route('produk.list'))));
        });
        $form->builder()->setResourceId($id);
        $form->builder()->setTitle($data->nama)->setMode('edit');
        $form->display('nama', __('Nama produk'))->setWidth(4)->value($data->nama);
        $form->select('default_unit', 'Satuan')->disable()->required()->setWidth(4)->options((new Dynamic)->setTable('toko_griyanaura.lv_unit')->select('kode_unit as id', 'nama as text')->pluck('text', 'id')->toArray())->value($data->default_unit);
        $form->display('deskripsi')->setWidth(6)->value($data->deskripsi);
        $form->switch('in_stok', 'Produk di-stok?')->disable()->states([
            'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
            'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
        ])->value($data->in_stok);
        $form->divider();
        $form->tablehasmany('produkHarga', 'Jenis Harga', function (NestedForm $form) {
            if($form->model()?->id_varianharga == 1) {
                $form->select('id_varianharga', 'Jenis')->readOnly()->required()->options((new Dynamic())->setTable('toko_griyanaura.lv_varianharga')->select('id_varianharga as id', 'nama as text')->get()->pluck('text', 'id')->toArray())->attribute([
                    'data-index' => $form->model()?->index
                ]);
            } else {
                $form->select('id_varianharga', 'Jenis')->required()->options((new Dynamic())->setTable('toko_griyanaura.lv_varianharga')->select('id_varianharga as id', 'nama as text')->get()->pluck('text', 'id')->toArray())->attribute([
                    'data-index' => $form->model()?->index
                ]);
            }
        })->value($data->produkHarga->map(fn ($item, $index) => array_merge($item->toArray(), ['index' => $index]))->toArray())->useTable();
        $form->tablehasmany('produkVarian', 'Varian Produk', function (NestedForm $form)  use ($data) {
            $form->text('kode_produkvarian')->withoutIcon()->readonly()->disable();
            $form->text('varian')->withoutIcon()->readonly()->disable();
            if ($form->model()) {
                foreach ($data->produkHarga as $key => $jenisHarga) {
                    $produkVarianHarga = array_filter($form->model()?->getAttribute('produk_varian_harga'), function ($varianHarga) use ($jenisHarga) { return $varianHarga['id_produkharga'] == $jenisHarga['id_produkharga']; });
                    $produkVarianHarga = array_pop($produkVarianHarga);
                    $form->currency('harga_jual_' . $jenisHarga['id_produkharga'], 'Harga Jual ' . $jenisHarga['nama'])->symbol('Rp')->default($produkVarianHarga['hargajual']??0);
                    $form->currency('harga_beli_' . $jenisHarga['id_produkharga'], 'Harga Modal ' . $jenisHarga['nama'])->symbol('Rp')->default($produkVarianHarga['hargabeli']??0);
                }
            } else {
                foreach ($data->produkHarga as $key => $jenisHarga) {
                    $form->currency('harga_jual_' . $jenisHarga['id_produkharga'], 'Harga Jual ' . $jenisHarga['nama'])->setLabelClass(['varianharga', 'index-varianharga-' . $key])->symbol('Rp');
                    $form->currency('harga_beli_' . $jenisHarga['id_produkharga'], 'Harga Modal ' . $jenisHarga['nama'])->setLabelClass(['varianharga', 'index-varianharga-' . $key])->symbol('Rp');
                }
            }
        })->value($data->produkVarian->toArray())
        ->disableDelete()
        ->disableCreate()
        ->useTable();
        return $form;
    }

    public function listProduk(Request $request, Content $content)
    {
        $style =
        <<<STYLE
            .filter-box .box-footer .row .col-md-2 {
                display : none;
            }    
        STYLE;
        $script =
        <<<SCRIPT
            $('[select2].form-control').each(function () {
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
        SCRIPT;
        Admin::style($style);
        Admin::script($script);
        return $content
            ->title('Produk')
            ->description('Daftar')
            ->row(function (Row $row) {
                $row->column(12, function (Column $column) {
                    $column->row($this->listProdukGrid());
                });
            });
    }
    public function showProduk(Content $content, $id)
    {
        $style =
        <<<STYLE
                    .input-group { 
                        width: 100%; 
                    }
                    table.has-many-produkAttribut tbody td:nth-child(1) div {
                        width:150px;
                    }
                    table.has-many-produkAttribut tbody td:nth-child(2) div {
                        width:300px;
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
                    .box-footer {
                        display: none;
                    }
        STYLE;
        $script =
        <<<SCRIPT
                    $('[varian-filter]').change(function () {
                        let attrValFilter = [];
                        $('[varian-filter]').each(function (k, varian) {
                            attrValFilter.push(varian.value);
                        })
                        $('#has-many-produkVarian table tbody tr').each(function (k, tr) {
                            $(tr).addClass('d-none');
                        })
                        $('#has-many-produkVarian table tbody tr').each(function (k, row) {
                            let cond = true;
                            $(row).find('td select[varian]').each(function (i, select) {
                                if (attrValFilter[i] != select.dataset.value && attrValFilter[i] != '' && select.dataset.value != '') {
                                    cond = false;
                                } 
                            });
                            if (cond) {
                                $(row).removeClass('d-none');
                            }
                        });
                    });
                    $('[select2].form-control').each(function () {
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
        SCRIPT;
        Admin::style($style);
        Admin::script($script);
        return $content
            ->title('Produk')
            ->description('Detail')
            ->body($this->showProdukForm($id));
    }
    public function createProduk(Content $content, Request $request)
    {
        $style =
        <<<STYLE
                    .input-group { 
                        width: 100%; 
                    }
                    table.has-many-produkAttribut tbody td:nth-child(1) div {
                        width:150px;
                    }
                    table.has-many-produkAttribut tbody td:nth-child(2) div {
                        width:300px;
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
        STYLE;
        $routeAttrVal = route(admin_get_route('ajax.attribut-value'));
        $script =
        <<<SCRIPT
                    function generateKombinasiVarian () {
                        let attrVals = [];
                        $('.id_attributvalue').each(function (i, select) {
                            if ($(select).val()) {
                                attrVals[i] = $(select).find('option:selected').toArray().map(item => item.text);
                            }
                        });
                        function getCombinations(arr) {
                            return arr.reduce((acc, curr) => {
                                const combinations = [];
                                acc.forEach(a => {
                                curr.forEach(b => {
                                    combinations.push([...a, b]);
                                });
                                });
                                return combinations;
                            }, [[]]);
                        }
                        const combinations = getCombinations(attrVals);

                        $('#has-many-produkVarian tbody tr:not(:first)').each(function (i, varian) {
                            removeProdukVarian(varian);
                        })
                        for (let i = 0; i < combinations.length - 1; i++) {
                            addProdukVarian();
                        }
                        
                        combinations.forEach(function (kombinasi, i) {
                            $('#has-many-produkVarian tbody #varian')[i].innerText = kombinasi.join('/');
                        });
                        if (combinations[0].length == 0) {
                            $('#has-many-produkVarian tbody #varian')[0].innerText = '--Tidak Ada--';
                        }
                    }
                    function removeProdukVarian(varian) {
                        var first_input_name = $(varian).closest('.has-many-produkVarian-form').find('input[name]:first').attr('name');
                        if (first_input_name.match('produkVarian\\\[new_')) {
                            $(varian).closest('.has-many-produkVarian-form').remove();
                        } else {
                            $(varian).closest('.has-many-produkVarian-form').hide();
                            $(varian).closest('.has-many-produkVarian-form').find('.fom-removed').val(1);
                            $(varian).closest('.has-many-produkVarian-form').find('input').removeAttr('required');
                        }
                        return false;
                    }
                    function addProdukVarian() {
                        let hargaBeli = $('input[name="default_hargabeli"]').val();
                        let hargaJual = $('input[name="hargajual"]').val();
                        let minStok = $('input[name="minstok"]').val();
                        var tpl = $('template.produkVarian-tpl');
                        index++;
                        var template = tpl.html().replace(/__LA_KEY__/g, index);
                        $(template).find('input#hargajual')[0].value = hargaJual;
                        $(template).find('input#default_hargabeli')[0].value = hargaBeli;
                        $('.has-many-produkVarian-forms').append(template);
                        $('.produkVarian.hargajual').inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
                        $('.produkVarian.default_hargabeli').inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
                        $('input[name="produkVarian[new_'+index+'][hargajual]"]').val(hargaJual);
                        $('input[name="produkVarian[new_'+index+'][default_hargabeli]"]').val(hargaBeli);
                        $('input[name="produkVarian[new_'+index+'][minstok]"]').val(minStok);
                        return false;
                    }
                    $('input[name="kode_produk"]').on('change', function () {
                        $('#has-many-produkVarian tbody td:first-child input')[0].value = this.value;
                    });
                    $('input[name="hargajual"]').on('change', function () {
                        const hargajual = this.value;
                        $('#has-many-produkVarian tbody td:nth-child(3) input').each(function (i, inp) {
                            inp.value = hargajual;
                        })
                    });
                    $('input[name="default_hargabeli"]').on('change', function () {
                        const hargabeli = this.value;
                        $('#has-many-produkVarian tbody td:nth-child(4) input').each(function (i, inp) {
                            inp.value = hargabeli;
                        })
                    });
                    $('input[name="minstok"]').on('change', function () {
                        const minStok = this.value;
                        $('#has-many-produkVarian tbody td:nth-child(6) input').each(function (i, inp) {
                            inp.value = minStok;
                        })
                    });
                    $('input[name="in_stok"]').on('change', function () { 
                        if (this.value == 'on') {
                            $('input[name="minstok"]').closest('.form-group').removeClass('d-none');
                            $('#has-many-produkVarian td:nth-last-child(-n + 3)').removeClass('d-none');
                            $('#has-many-produkVarian th:nth-last-child(-n + 3)').removeClass('d-none');
                        } else {
                            $('input[name="minstok"]').closest('.form-group').addClass('d-none'); 
                            $('#has-many-produkVarian td:nth-last-child(-n + 3)').addClass('d-none');
                            $('#has-many-produkVarian th:nth-last-child(-n + 3)').addClass('d-none');
                        }
                    });
                    $('input[name="has_varian"]').on('change', function () {
                        if (this.value == 'on') {
                            $('input[name="kode_produk"]').attr('disabled', true);
                            $('input[name="hargajual"]').attr('disabled', true);
                            $('input[name="default_hargabeli"]').attr('disabled', true);
                            $('#produk-varian-section').removeClass('d-none');
                        } else {
                            $('input[name="kode_produk"]').attr('disabled', false);
                            $('input[name="hargajual"]').attr('disabled', false);
                            $('input[name="default_hargabeli"]').attr('disabled', false);
                            $('#produk-varian-section').addClass('d-none');
                        }
                    });
                    $('#has-many-produkVarian tr:first-child input#kode_produkvarian').on('change', function () {
                        $('input[name="kode_produk"]').val($(this).val());
                    });
                    $('#has-many-produkVarian tr:first-child input#hargajual').on('change', function () {
                        $('input[name="hargajual"]').val($(this).val());
                    });
                    $('#has-many-produkVarian tr:first-child input#default_hargabeli').on('change', function () {
                        $('input[name="default_hargabeli"]').val($(this).val());
                    });
                    $('#has-many-produkVarian tr:first-child input#minstok').on('change', function () {
                        $('input[name="minstok"]').val($(this).val());
                    });
                    $('.has-many-produkAttribut tbody tr:first-child td .remove').removeClass('remove').addClass('disabled remove-disabled');
                    $('.has-many-produkVarian tbody tr:first-child td .remove').removeClass('remove').addClass('disabled remove-disabled');
                    $(document).on('change', ".id_attributvalue", generateKombinasiVarian);
                    $('#has-many-produkAttribut').on('click', '.remove', function () {
                        var first_input_name = $(this).closest('.has-many-produkAttribut-form').find('input[name]:first').attr('name');
                        if (first_input_name.match('produkAttribut\\\[new_')) {
                            $(this).closest('.has-many-produkAttribut-form').remove();
                        } else {
                            $(this).closest('.has-many-produkAttribut-form').hide();
                            $(this).closest('.has-many-produkAttribut-form').find('.fom-removed').val(1);
                            $(this).closest('.has-many-produkAttribut-form').find('input').removeAttr('required');
                        }
                        generateKombinasiVarian();
                        return false;
                    });
                    $(document).off('change', ".id_attribut");
                    $(document).on('change', ".id_attribut", function () {
                        var target = $(this).closest('tr').find('.id_attributvalue');
                        target.removeClass('produkAttribut'); //hapus class tsb. agar select tidak teroverwrite ketika tambah row
                        target.find("option").remove();
                        $(target).select2({
                        ajax: {
                            url: "$routeAttrVal/"+this.value,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                            return {
                                q: params.term,
                                page: params.page
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
                        "placeholder":"Nilai Varian",
                        "minimumInputLength":1,
                        escapeMarkup: function (markup) {
                            return markup;
                        }
                        });
                        if (target.data('value')) {
                            $(target).val(target.data('value'));
                        }
                        $(target).trigger('change');
                    });
                    $('[select2].form-control').each(function () {
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
        SCRIPT;
        Admin::style($style);
        Admin::script($script);
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->createProdukForm($request->all())->setAction(route(admin_get_route('produk.store'))));
    }
    public function editProduk(Content $content, Request $request, $id)
    {
        $urlAjaxAttrVal = route(admin_get_route('ajax.attribut-value'));
        $style =
        <<<STYLE
                    .input-group { 
                        width: 100%; 
                    }
                    [id^="has-many-"] table td:has([varian]) {
                        min-width: 150px;
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
        STYLE;
        $script = 
        <<<SCRIPT
                $('[select2][akun].form-control').each(function () {
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
                $('#has-many-produkAttribut').on('click', '.remove', function () {
                    const row = $(this).closest('tr'); // get baris yang ada tombol diklik
                    const index = row.find('select')[0].dataset.index; // get index
                    const columnIndex = $('#has-many-produkVarian thead th.index-varian-' + index).index();
                    $('#has-many-produkVarian thead tr').each(function() {
                        $(this).find('th').eq(columnIndex).text('');
                        $(this).find('th').eq(columnIndex).addClass('hidden');
                    });
                    $('#has-many-produkVarian tbody tr').each(function() {
                        $(this).find('td').eq(columnIndex).addClass('hidden');
                        $(this).find('td').eq(columnIndex).find('select').attr('required', false);
                    });
                    let produkVarianTpl = $($('template.produkVarian-tpl')[0].content);
                    produkVarianTpl.find('td').eq(columnIndex).addClass('hidden');
                });
        SCRIPT;
        $deferredScript =
        <<<SCRIPT
                $('#has-many-produkVarian').on('click', '.add', function () {
                    $(".produkVarian[varian]").select2({
                        ajax: {
                            url: "{$urlAjaxAttrVal}",
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                            return {
                                q: params.term,
                                page: params.page
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
                        "allowClear":true,
                        "placeholder": "",
                        "minimumInputLength":1,
                        escapeMarkup: function (markup) {
                            return markup;
                        }
                    });
                });
                $('#has-many-produkVarian').on('click', '.remove', function () {
                    $(this).closest('tr').find('[required]').attr('required', false);
                });
                $('#has-many-produkAttribut').on('click', '.add', function () {
                    const nextIndex = parseInt($('#has-many-produkAttribut tbody tr').filter(':visible').eq(-2).find('select')[0]?.dataset.index || '-1') + 1;
                    $('#has-many-produkAttribut tbody tr').filter(':visible').eq(-1).find('select').attr('data-index', nextIndex);
                    
                    let newAttribut = 'new_' + index;
                    if ($('#has-many-produkVarian thead th.index-varian-' + nextIndex).length > 0) {
                        /* Menggunakan kolom yang sudah dihapus */
                        const columnIndex = $('#has-many-produkVarian thead th.index-varian-' + nextIndex).index();
                        $('#has-many-produkVarian thead tr').each(function() {
                            $(this).find('th').eq(columnIndex).removeClass('hidden');
                        });
                        $('#has-many-produkVarian tbody tr').filter(':visible').each(function() {
                            let kodeProdukvarian = $(this).find('td').eq(columnIndex).find('select')[0].dataset.kodeProdukvarian;
                            $(this).find('td').eq(columnIndex).find('input').attr('name', 'produkVarian['+kodeProdukvarian+']['+newAttribut+']');
                            $(this).find('td').eq(columnIndex).find('select').attr('name', 'produkVarian['+kodeProdukvarian+']['+newAttribut+']');
                            $(this).find('td').eq(columnIndex).find('select').attr('required', true);
                            $(this).find('td').eq(columnIndex).removeClass('hidden');
                        });
                        let produkVarianTpl = $($('template.produkVarian-tpl')[0].content);
                        produkVarianTpl.find('td').eq(columnIndex).find('input').attr('name', 'produkVarian[new___LA_KEY__]['+newAttribut+']');    
                        produkVarianTpl.find('td').eq(columnIndex).find('select').attr('name', 'produkVarian[new___LA_KEY__]['+newAttribut+']');    
                        produkVarianTpl.find('td').eq(columnIndex).removeClass('hidden');
                    } else {
                        let columnIndex = $('#has-many-produkVarian thead th.varian').last().index();
                        if(columnIndex == -1) {
                            columnIndex = 0;
                        }
                        $('#has-many-produkVarian thead tr').each(function() {
                            $(this).find('th').eq(columnIndex).after('<th class="varian index-varian-' + nextIndex + '">');
                        });
                        $('#has-many-produkVarian tbody tr').filter(':visible').each(function() {
                            let lastVarianCell = $(this).find('td').eq(columnIndex);
                            let kodeProdukVarian = $(this).find('td.hidden input').eq(0).val();
                            console.info(lastVarianCell, kodeProdukVarian)
                            let selectedValue = lastVarianCell.find('select')[0]?.value || '';
                            let selectedIndex = lastVarianCell.find('select')[0]?.selectedIndex || 0;
                            let selectedValueText = lastVarianCell.find('select')[0]?.options[selectedIndex].text;
                            let newCell = `
                                <td>
                                    <div class="form-group">
                                        <label for="`+ newAttribut +`" class="col-sm-0 varian hidden control-label">Warna</`+`label>
                                        <div class="col-sm-12">
                                            <input type="hidden" name="produkVarian[`+ kodeProdukVarian +`][`+ newAttribut +`]">
                                            <select required class="form-control produkVarian `+ newAttribut +`" style="width: 100%;" name="produkVarian[`+ kodeProdukVarian +`][`+ newAttribut +`]" varian="" data-kode-produkvarian="` + kodeProdukVarian + `" data-index-varian="`+ nextIndex +`" data-value="">
                                                <option value="` + selectedValue + `" selected>` + selectedValueText + `</option>
                                            </select>
                                        </`+`div>
                                    </`+`div>
                                </td>`;
                            lastVarianCell.after(newCell);
                        });
                        let newCellTemplate = `
                            <td>
                                <div class="form-group">
                                    <label for="`+ newAttribut +`" class="col-sm-0 varian hidden control-label"></`+`label>
                                    <div class="col-sm-12">
                                        <input type="hidden" name="produkVarian[new___LA_KEY__][`+ newAttribut +`]">
                                        <select required class="form-control produkVarian `+ newAttribut +`" style="width: 100%;" name="produkVarian[new___LA_KEY__][`+ newAttribut +`]" varian="" data-kode-produkvarian="new___LA_KEY__" data-index-varian="`+ nextIndex +`" data-value="">
                                            <option value=""></option>
                                        </select>
                                    </`+`div>
                                </`+`div>
                            </td>`;
                        let produkVarianTplCloned = $($('template.produkVarian-tpl').html());
                        produkVarianTplCloned.find('td').eq(columnIndex).after(newCellTemplate);
                        $('template.produkVarian-tpl').remove() // menghapus template lama, document fragment is suck!
                        $('#has-many-produkVarian').append('<template class="produkVarian-tpl"><tr class="has-many-produkVarian-form fields-group">' + produkVarianTplCloned.html() + '</tr></template>'); // membuat template baru
                        
                        $(".produkVarian." + newAttribut).select2({
                            ajax: {
                                url: "{$urlAjaxAttrVal}",
                                dataType: 'json',
                                delay: 250,
                                data: function (params) {
                                return {
                                    q: params.term,
                                    page: params.page
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
                            "allowClear":true,
                            "placeholder": "",
                            "minimumInputLength":1,
                            escapeMarkup: function (markup) {
                                return markup;
                            }
                        });
                    }
                    
                    return false;
                });
                $('#has-many-produkAttribut').on('change', 'select', function () {
                    let indexVarian = this.dataset.index;
                    let varianText = this.options[this.selectedIndex].text;
                    $('#has-many-produkVarian thead th.index-varian-' + indexVarian).text(varianText);
                });
        SCRIPT;
        
        Admin::style($style);
        Admin::script($script, false);
        Admin::script($deferredScript, true);
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->editProdukForm($id, $request->all())->setAction(route(admin_get_route('produk.update'), ['id' => $id])));
    }
    public function editHargaProduk($id, Request $request, Content $content) {
        $style = 
        <<<STYLE
                    .duplicate {
                        border-color: red !important;
                    }
                    .input-group { 
                        width: 100%; 
                    }
                    [id^="has-many-"] table td {
                        min-width: 150px;
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
        STYLE;
        $script = 
        <<<SCRIPT
            function checkDuplicateHarga() {
                let valueCount = {};
                $('#has-many-produkHarga select').filter(':visible').each(function () {
                    let value = $(this).val();
                    valueCount[value] = (valueCount[value] || 0) + 1;
                });
                console.info(valueCount);
                $('#has-many-produkHarga select').filter(':visible').each(function () {
                    let value = $(this).val();
                    if (valueCount[value] > 1) {
                        $(this).next().find('span.select2-selection').addClass('duplicate');
                    } else {
                        $(this).next().find('span.select2-selection').removeClass('duplicate');
                    }
                });
            }
            function disableButtons() {
                let removeButtons = $('#has-many-produkHarga tbody .btn');  // Ambil semua elemen tombol
                
                if (removeButtons.length === 1) {
                    // Jika hanya ada satu tombol, disable tombol tersebut
                    removeButtons.removeClass('remove').addClass('remove-disable disabled');
                } else {
                    // Disable semua tombol kecuali yang terakhir
                    removeButtons.slice(0, -1).removeClass('remove').addClass('remove-disable disabled');  // Tombol selain terakhir di-disable
                    removeButtons.last().addClass('remove').removeClass('remove-disable disabled');  // Tombol terakhir diaktifkan
                }
            }
            disableButtons();
            checkDuplicateHarga();
            $('#has-many-produkHarga tbody').on('change', 'select', function () {
                checkDuplicateHarga();
            });
            $('#has-many-produkHarga').on('change', 'select', function () {
                let indexVarian = this.dataset.index;
                let varianText = this.options[this.selectedIndex].text;
                $('#has-many-produkVarian thead th.index-varianharga-' + indexVarian).each(function(k,el) {
                    let harga;
                    if (k == 0) {
                        harga = 'Harga Jual ';
                    } else {
                        harga = 'Harga Modal ';
                    }
                    $(this).text(harga + varianText);
                });
            });
            $('#has-many-produkHarga').on('click', '.remove', function () {
                const row = $(this).closest('tr'); // get baris yang ada tombol diklik
                const index = row.find('select')[0].dataset.index; // get index
                const columnIndex = $('#has-many-produkVarian thead th.index-varianharga-' + index).index();
                $('#has-many-produkVarian thead tr').each(function() {
                    $(this).find('th').eq(columnIndex).text('Harga Jual ');
                    $(this).find('th').eq(columnIndex).addClass('hidden');
                    $(this).find('th').eq(columnIndex+1).text('Harga Modal ');
                    $(this).find('th').eq(columnIndex+1).addClass('hidden');
                });
                $('#has-many-produkVarian tbody tr').each(function() {
                    $(this).find('td').eq(columnIndex).addClass('hidden');
                    $(this).find('td').eq(columnIndex).find('input').attr('required', false);
                    $(this).find('td').eq(columnIndex+1).addClass('hidden');
                    $(this).find('td').eq(columnIndex+1).find('input').attr('required', false);
                });
            });
        SCRIPT;
        $deferredScript = 
        <<<SCRIPT
            $('#has-many-produkHarga').on('click', '.remove', function () {
                checkDuplicateHarga();
                disableButtons();
            });
            $('#has-many-produkHarga').on('click', '.add', function () {
                const nextIndex = parseInt($('#has-many-produkHarga tbody tr').filter(':visible').eq(-2).find('select')[0]?.dataset.index || '-1') + 1;
                $('#has-many-produkHarga tbody tr').filter(':visible').eq(-1).find('select').attr('data-index', nextIndex);
                
                let newAttribut = 'new_' + index;
                if ($('#has-many-produkVarian thead th.index-varianharga-' + nextIndex).length > 0) {
                    /* Menggunakan kolom yang sudah dihapus */
                    const columnIndex = $('#has-many-produkVarian thead th.index-varianharga-' + nextIndex).index();
                    $('#has-many-produkVarian thead tr').each(function() {
                        $(this).find('th').eq(columnIndex).removeClass('hidden');
                        $(this).find('th').eq(columnIndex+1).removeClass('hidden');
                    });
                    $('#has-many-produkVarian tbody tr').filter(':visible').each(function() {
                        let kodeProdukvarian = $(this).find('input').first().val();
                        $(this).find('td').eq(columnIndex).find('input').attr('name', 'produkVarian['+kodeProdukvarian+']['+newAttribut+']').attr('required', true);
                        $(this).find('td').eq(columnIndex).removeClass('hidden');
                        $(this).find('td').eq(columnIndex+1).find('input').attr('name', 'produkVarian['+kodeProdukvarian+']['+newAttribut+']').attr('required', true);
                        $(this).find('td').eq(columnIndex+1).removeClass('hidden');
                    });
                } else {
                    let columnIndex = $('#has-many-produkVarian thead th.varianharga').last().index();
                    if(columnIndex == -1) {
                        columnIndex = 0;
                    }
                    $('#has-many-produkVarian thead tr').each(function() {
                        $(this).find('th').eq(columnIndex).after('<th class="varianharga index-varianharga-' + nextIndex + '">Harga Jual </th>');
                        $(this).find('th').eq(columnIndex+1).after('<th class="varianharga index-varianharga-' + nextIndex + '">Harga Modal </th>');
                    });
                    $('#has-many-produkVarian tbody tr').filter(':visible').each(function() {
                        let kodeProdukvarian = $(this).find('input').first().val();
                        let lastCell = $(this).find('td').eq(columnIndex);

                        let lastHargaJual = $(this).find('td').eq(columnIndex-1);
                        let valueJual = lastHargaJual.find('input')[0]?.value || '';
                        let lastHargaBeli = $(this).find('td').eq(columnIndex);
                        let valueBeli = lastHargaBeli.find('input')[0]?.value || '';

                        let newCellJual = `
                            <td>
                                <div class="form-group ">
                                    <label for="harga_jual_`+newAttribut+`" class="col-sm-0 hidden control-label">Harga Jual<`+`/label>
                                    <div class="col-sm-12">
                                        <div class="input-group"><span class="input-group-addon">Rp<`+`/span>
                                            
                                            <input style="width: 120px; text-align: right;" type="text" id="harga_jual_`+newAttribut+`" name="produkVarian[`+kodeProdukvarian+`][harga_jual_`+newAttribut+`]" value="`+valueJual+`" class="form-control produkVarian harga_jual_`+newAttribut+`" placeholder="Input Harga Jual">

                                            
                                        <`+`/div>

                                        
                                    <`+`/div>
                                <`+`/div>
                            <`+`/td>
                        `;
                        let newCellBeli = `
                            <td>
                                <div class="form-group ">
                                    <label for="harga_beli_`+newAttribut+`" class="col-sm-0 hidden control-label">Harga Modal<`+`/label>
                                    <div class="col-sm-12">
                                        <div class="input-group"><span class="input-group-addon">Rp<`+`/span>
                                            
                                            <input style="width: 120px; text-align: right;" type="text" id="harga_beli_`+newAttribut+`" name="produkVarian[`+kodeProdukvarian+`][harga_beli_`+newAttribut+`]" value="`+valueBeli+`" class="form-control produkVarian harga_beli_`+newAttribut+`" placeholder="Input Harga Modal">

                                            
                                        <`+`/div>

                                        
                                    <`+`/div>
                                <`+`/div>
                            <`+`/td>
                        `;
                        lastCell.after(newCellBeli).after(newCellJual); // harus dibalik urutannya
                    });
                    $('.produkVarian.harga_jual_'+newAttribut).inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
                    $('.produkVarian.harga_beli_'+newAttribut).inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
                }
                checkDuplicateHarga();
                disableButtons();
                return false;
            });
        SCRIPT;
        Admin::style($style);
        Admin::script($script);
        Admin::script($deferredScript, true);
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->editHargaProdukForm($id, $request->all())->setAction(route(admin_get_route('produk.update.harga'), ['id' => $id])));
    }
    public function updateProduk($id, Request $request)
    {
        try {
            $this->produkService->updateProduk($id, $request->all());
            admin_toastr('Sukses memperbarui produk');
            return redirect()->route(admin_get_route('produk.detail'), ['id' => $id]);
        } catch (ValidationException $e) {
            dd($e->validator->getMessageBag());
            return back()->withInput(request()->only(['nama', 'deskripsi', 'namaunit']))->withErrors($e->validator);
        }
        // $validator = Validator::make($request->all(), [
        //     'nama' => 'required|min:3'
        // ]);
        // if ($validator->fails()) {
        //     return back()->withInput(request()->only(['nama', 'deskripsi']))->withErrors($validator);
        // }
        // return dump($request->all());
        // admin_toastr('Sukses update produk');
        // return redirect()->route(admin_get_route('produk.list'));
        // return $this->createProdukForm()->update($id);
    }
    public function updateProdukHarga($id, Request $request)
    {
        try {
            $this->produkService->updateProdukHarga($id, $request->all());
            admin_toastr('Sukses memperbarui harga produk');
            return redirect()->route(admin_get_route('produk.detail'), ['id' => $id]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->validator);
        }
    }
    public function storeProduk(Request $request)
    {
        try {
            $idProduk = $this->produkService->storeProduk($request->all());
            admin_toastr('Sukses membuat produk');
            return redirect()->route(admin_get_route('produk.detail'), ['id' => $idProduk]);
        } catch (ValidationException $e) {
            return back()->withInput(request()->only(['nama', 'deskripsi', 'namaunit']))->withErrors($e->validator);
        }
    } 
    public function deleteProduk($id, Request $request)
    {
        try {
            $this->produkService->deleteProduk($id);
            admin_toastr('Sukses hapus produk');
            return [
                'status' => true,
                'then' => ['action' => 'refresh', 'value' => true],
                'message' => 'Sukses hapus produk'
            ];
            // return redirect()->route(admin_get_route('produk.list'));
        } catch (ValidationException $e) {
            return back()->withInput(request()->only(['nama', 'deskripsi', 'namaunit']))->withErrors($e->validator);
        }
    }
}
