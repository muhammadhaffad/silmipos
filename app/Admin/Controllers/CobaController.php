<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Table\InlineCancelEdit;
use App\Admin\Actions\Table\InlineDelete;
use App\Admin\Actions\Table\InlineEdit;
use App\Admin\Actions\Table\InlineSave;
use App\Models\ProdukVarian;
use Encore\Admin\Actions\Action;
use Encore\Admin\Actions\Response;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Controllers\Dashboard;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Encore\Admin\Show;
use Encore\Admin\Widgets\Form as WidgetsForm;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CobaController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'ProdukVarian';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $user = Auth::user();
        $form = new WidgetsForm();
        $tableName = 'table1';
        $grid = new Grid(new ProdukVarian());
        $grid->model()->select('kode_produkvarian','prd.nama','hargajual')->join('toko_griyanaura.ms_produk as prd', 'toko_griyanaura.ms_produkvarian.id_produk', 'prd.id_produk');
        $grid->setName($tableName);
        $grid->column('nama')->sortable();
        $grid->column('kode_produkvarian', __('Kode Produk'))->display(function ($value) use ($form, $tableName) {
            if (isset($_GET[$tableName . '_inline_edit']) and $_GET[$tableName . '_inline_edit'] == $this->getKey()) {
                return $form->text('kode_produkvarian',null)
                    ->setWidth(0)->attribute('inline-edit',$tableName . '_' . $this->getKey())->setGroupClass('mb-0')->setLabelClass(['d-none'], true)->withoutIcon()->value($value)->render();
            } else {
                return $value;
            }
        })->sortable();
        $grid->column('hargajual')->display(function ($value) use ($form, $tableName) {
            if (isset($_GET[$tableName . '_inline_edit']) and $_GET[$tableName . '_inline_edit'] == $this->getKey()) {
                return $form->text('hargajual',null)
                    ->setWidth(0)->attribute('inline-edit',$tableName . '_' . $this->getKey())->setGroupClass('mb-0')->setLabelClass(['d-none'], true)->withoutIcon()->value($value)->render();
            } else {
                return $value;
            }
        })->sortable();
        $grid->actions(function ($actions) use($tableName) {
            $actions->disableEdit();
            $actions->disableView();
            $actions->disableDelete();
            if (isset($_GET[$tableName . '_inline_edit']) and $_GET[$tableName . '_inline_edit'] == $this->getKey() and Admin::user()->can('coba-table.update')) {
                $actions->add(new InlineSave($tableName, route(admin_get_route('test.handler'), ['a'=>11])));
                $actions->add(new InlineCancelEdit($tableName));
            } else {
                if (Admin::user()->can('coba-table.update')) {
                    $actions->add(new InlineEdit($tableName));
                }
                if (Admin::user()->can('coba-table.delete')) {
                    $actions->add(new InlineDelete($tableName, route(admin_get_route('test.handler'), ['a'=>11])));
                }
            }
        });
        return $grid;
    }
    protected function grid2()
    {
        $grid = new Grid(new ProdukVarian());
        $grid->setName('table2');
        $grid->filter(function ($filter) {
            $filter->expand();
            $filter->disableIdFilter();
            $filter->like('kode_produkvarian', 'Kode Produk');
        });
        $grid->column('kode_produkvarian', __('Kode Produk'));
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(ProdukVarian::findOrFail($id));



        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ProdukVarian());



        return $form;
    }

    public function cobaTable(Request $request, Content $content) {
        return $content
            ->title($this->title())
            ->description('List')
            ->row(function (Row $row) {
                $row->column(6, function (Column $column) {
                    $column->row($this->grid());
                });
                // $row->column(6, function (Column $column) {
                //     $column->row($this->grid2());
                // });
            });
    }

    public function cobaHandle(Request $request) {
        DB::table('toko_griyanaura.ms_produkvarian')->where('kode_produkvarian', $request['_key'])->update([
            'hargajual' => $request['hargajual']
        ]);
        return (new Response())->toastr()->success('asd')->refresh()->send();
    }
}
