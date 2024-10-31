<?php

namespace App\Admin\Controllers;

use App\Admin\Actions\Grid\Delete;
use App\Admin\Actions\Grid\Edit;
use App\Admin\Actions\Grid\Show;
use App\Exceptions\SalesOrderException;
use App\Models\Dynamic;
use App\Models\Jurnal;
use App\Models\Penjualan;
use App\Models\ProdukPersediaan;
use App\Models\ProdukPersediaanDetail;
use App\Services\Core\Sales\SalesOrderService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\Footer;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Form\Tools;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Displayers\DropdownActions;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends AdminController
{
    public function listDetailLedgerGrid() {
        $grid = new Grid(new Jurnal);
        $grid->model()->with(['akun', 'transaksi.transaksiJenis']);
        if (!isset($_GET['_sort']['column']) and empty($_GET['sort']['column'])) {
            $grid->model()->orderByRaw('tanggal desc, id_jurnal asc');
        }
        $grid->column('transaksi.transaksi_no', 'Kode Transaksi');
        $grid->column('transaksi.transaksi_jenis', 'Jenis Transaksi')->display(function ($val) {
            return $val['nama'];
        });
        $grid->column('akun.nama', 'Akun');
        $grid->column('tanggal', 'Tanggal')->display(function ($val) {
            return \date('d F Y', \strtotime($val));
        })->sortable();
        $grid->column('keterangan', 'Keterangan');
        $grid->column('nominaldebit', 'Debit')->display(function ($val) {
            return 'Rp' . number_format($val, 0, ',', '.');
        });
        $grid->column('nominalkredit', 'Kredit')->display(function ($val) {
            return 'Rp' . number_format($val, 0, ',', '.');
        });
        $grid->disableCreateButton();
        $grid->disableBatchActions();
        $grid->disableActions();
        return $grid;
    }
    public function listDetailLedger(Content $content) {
        return $content
            ->title('Detail Jurnal')
            ->description('Daftar')
            ->row(function (Row $row) {
                $row->column(12, function (Column $column) {
                    $column->row($this->listDetailLedgerGrid());
                });
            });
    }
}
