<?php

namespace App\Admin\Controllers;

use App\Admin\Forms\Produk\AkuntansiProduk;
use App\Admin\Forms\Produk\InformasiProduk;
use App\Http\Controllers\Controller;
use App\Models\Dynamic;
use App\Models\Produk;
use Encore\Admin\Admin;
// use App\Admin\Form\Form;
use Encore\Admin\Form;
use Encore\Admin\Form\NestedForm;
use Encore\Admin\Layout\Content;
use Encore\Admin\Widgets\Form as WidgetsForm;
use Encore\Admin\Widgets\Tab;
use Encore\Admin\Widgets\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Termwind\style;

class ProdukController extends Controller
{
    public function editProdukForm($id, $request)
    {
        $form = new Form(new Produk);
        $data = $form->model()->with('produkAttribut', 'produkVarian')->find($id);
        $form->builder()->setTitle($data->nama)->setMode('edit');
        $form->tab('Informasi Produk', function (Form $form) use ($data) {
            $form->text('nama', __('Nama produk'))->withoutIcon()->required()->value($data->nama);
            $form->ckeditor('deskripsi')->value($data->deskripsi);
            $form->switch('in_stok', 'Produk di-stok?')->disable()->states([
                'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->value($data->in_stok);
            $form->divider();
            $optionsVarian = array();
            foreach ($data->produkAttribut as $attribut) {
                $optionsVarian[$attribut['id_produkattribut']] = (new Dynamic)->setTable('toko_griyanaura.ms_produkattributvarian as pav')->join('toko_griyanaura.lv_attributvalue as av', 'pav.id_attributvalue', 'av.id_attributvalue')->where('id_produkattribut', $attribut['id_produkattribut'])->select('av.id_attributvalue as id', 'av.nama as text')->pluck('text', 'id')->toArray();
                $form->select($attribut->nama)->setWidth(4)
                    ->attribute('varian-filter')
                    ->options($optionsVarian[$attribut['id_produkattribut']]);
            }
            // $varianOptions = $data->produkVarian->pluck('varian', 'varian_id')->toArray();
            $form->hasMany('produkVarian', '', function (NestedForm $form) use ($data, $optionsVarian) {
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
                    $form->text('kode_produkvarian')->withoutIcon()->disable()->customFormat(function ($x) {
                        return $x;
                    })->setElementName("produkVarian[{$key}][{$keyName}]");
                    foreach ($data->produkAttribut->toArray() as $attr) {
                        $form->select($attr['id_produkattribut'], $attr['nama'])->placeholder($attr['nama'])->setScript('')->attribute('varian')->default($attributVarian[$attr['id_produkattribut']] ?? '')->options($optionsVarian[$attr['id_produkattribut']])->customFormat(function ($x) {
                            return $x;
                        })->disable();
                    }
                } else {
                    /* Jika tidak ada row, maka sama dengan tambah produk, otomatis select pakai ajax */
                    $form->text('kode_produkvarian')->withoutIcon()->customFormat(function ($x) {
                        return $x;
                    })->required()->setElementName("produkVarian[{$key}][{$keyName}]");
                    foreach ($data->produkAttribut->toArray() as $attr) {
                        $form->select($attr['id_produkattribut'], $attr['nama'])->placeholder($attr['nama'])->setScript('')->attribute('varian')->ajax(route(admin_get_route('ajax.attribut-value'), $attr['id_attribut']));
                    }
                }
                $form->currency('hargajual', 'Harga Jual')->symbol('Rp');
                if ($data->in_stok) {
                    $form->currency('default_hargabeli', 'Harga Beli')->symbol('Rp');
                    // $form->date('pertanggal', 'Per Tanggal');
                    $form->text('stok', 'Stok')->attribute('type', 'number')->attribute('step','.01')->style('min-width', '80px')->withoutIcon()->disable();
                    $form->text('minstok', 'Min Stok')->attribute('type', 'number')->attribute('step','.01')->style('min-width', '80px')->default('0.00')->withoutIcon();
                }
            })->value($data->produkVarian->toArray())->useTable();
        })->tab('Akuntansi', function (Form $form) use ($data) {
            $form->select('default_akunpersediaan', __('Akun persediaan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('1301')
                ->value($data->default_akunpersediaan);

            $form->select('default_akunpemasukan', __('Akun pemasukan'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('4001')
                ->value($data->default_akunpemasukan);

            $form->select('default_akunbiaya', __('Akun Biaya'))
                ->required()
                ->attribute('data-url', route('admin.ajax.akun'))
                ->attribute('select2')
                ->ajax(route(admin_get_route('ajax.akun')))
                ->default('5002')
                ->value($data->default_akunbiaya);
        });
        return $form;
    }
    public function editProduk(Content $content, Request $request, $id)
    {
        $style = 
<<<SCRIPT
            .input-group { 
                width: 100%; 
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
            [id^="has-many-"] .form-group {
                width: 100%;
                position: -webkit-sticky;
                position: sticky;
                left: 0px;
                background: white;
                z-index: 10;
            }
SCRIPT;
        $selectScript =
<<<SCRIPT
            $('[varian-filter]').change(function () {
                let attrValFilter = [];
                $('[varian-filter]').each(function (k, varian) {
                    attrValFilter.push(varian.value);
                })
                $('#has-many-produkVarian table tbody tr').each(function (k, tr) {
                    $(tr).addClass('d-none');
                })
                $('#has-many-produkVarian table tbody td select').each(function (k, select) {
                    if (attrValFilter.includes(select.dataset.value)) {
                        $(select).closest('tr').removeClass('d-none');
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
        Admin::script($selectScript);
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->editProdukForm($id, $request->all())->setAction(route(admin_get_route('produk.update'), ['id' => $id])));
    }
    public function updateProduk($id, Request $request)
    {
        dump($request->all());
        // return $this->createProdukForm()->update($id);
    }
}
