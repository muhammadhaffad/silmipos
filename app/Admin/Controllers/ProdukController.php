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
    public function createProdukForm($request)
    {
        $form = new Form(new Produk);
        $form->builder()->setTitle('Tambah Produk')->setMode('edit');
        $form->tab('Informasi Produk', function (Form $form) {
            $form->text('kode_produk', __('SKU'))->placeholder('[AUTO]')->withoutIcon()->setWidth(4);
            $form->text('nama', __('Nama produk'))->withoutIcon()->required();
            $form->select('default_unit', 'Satuan')->required()->setWidth(4)->options((new Dynamic)->setTable('toko_griyanaura.lv_unit')->select('kode_unit as id', 'nama as text')->pluck('text', 'id')->toArray());
            $form->ckeditor('deskripsi');
            $form->currency('hargajual', 'Harga jual')->symbol('Rp');
            $form->currency('default_hargabeli', 'Harga beli')->symbol('Rp');
            $form->switch('in_stok', 'Produk di-stok?')->states([
            'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
            'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->default(true);
            $form->text('minstok', 'Min. stok')->attribute('type', 'number')->withoutIcon()->width('100px');
            $form->switch('has_varian', 'Produk memiliki varian?')->states([
                'on' => ['value' => 1, 'text' => 'Iya', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => 'Tidak', 'color' => 'danger']
            ])->default(false);
            /* $form->html(function (Form $form) {
                $template = '<div class="row">';
                $template .= $form->select('produkattribut', 'Varian Produk')
                    ->setLabelClass(['d-none'])
                    ->setGroupClass('col-md-6')->setWidth(12)
                    ->options((new Dynamic())->setTable('toko_griyanaura.lv_attribut')->select('id_attribut as id', 'nama as text')->pluck('text', 'id')->toArray())->render();
                $template .= $form->multipleSelect('attributvalue', 'Nilai Varian')
                    ->setLabelClass(['d-none'])
                    ->setGroupClass('col-md-6')->setWidth(12)->render();
                // dd($template);
                $template .= '</div>';
                return $template;
            }, 'Varian Produk'); */
            $form->html('<div id="produk-varian-section" class="d-none">')->plain();
            $form->divider();
            $form->tablehasmany('produkAttribut', 'Varian Produk', function (NestedForm $form) {
                $form->select('id_attribut', 'Varian')->options((new Dynamic())->setTable('toko_griyanaura.lv_attribut')->select('id_attribut as id', 'nama as text')->pluck('text', 'id')->toArray())->required();
                $form->multipleSelect('id_attributvalue', 'Nilai Varian');
            })->value([
                ['id_produkattribut' => '']
            ])->useTable();
            $form->divider();
            $form->tablehasmany('produkVarian', '', function (NestedForm $form) {
                $keyName = 'kode_produkvarian_new';
                if ($form->model()) {
                    $key = $form->getKey();
                } else {
                    $key = 'new___LA_KEY__';
                }
                $form->text('kode_produkvarian', 'SKU')->style('width', '150px')->placeholder('[AUTO]')->withoutIcon()->customFormat(function ($x) {
                    return $x;
                })->required()->setElementName("produkVarian[{$key}][{$keyName}]");
                $form->html('<span id="varian">--Tidak Ada--</span>', 'Varian');
                $form->currency('hargajual', 'Harga Jual')->symbol('Rp');
                $form->currency('default_hargabeli', 'Harga Beli')->symbol('Rp');
                $form->text('stok', 'Stok')->attribute('type', 'number')->attribute('step','.01')->style('min-width', '80px')->default(0)->withoutIcon()->disable();
                $form->text('minstok', 'Min Stok')->attribute('type', 'number')->attribute('step','.01')->style('min-width', '80px')->default('0.00')->withoutIcon();
            })->disableDelete()->disableCreate()->value([
                [
                    'kode_produkvarian' => ''
                ]
            ])->useTable();
            $form->html('</div>')->plain();
        })->tab('Akuntansi', function (Form $form) {
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
        $data = $form->model()->with('produkAttribut', 'produkVarian')->find($id);
        $form->builder()->setTitle($data->nama)->setMode('edit');
        $form->tab('Informasi Produk', function (Form $form) use ($data) {
            $form->text('nama', __('Nama produk'))->withoutIcon()->required()->value($data->nama);
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
                    $form->text('stok', 'Stok')->attribute('type', 'number')->attribute('step','.01')->style('min-width', '80px')->default(0)->withoutIcon()->disable();
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
    public function createProduk(Content $content, Request $request) {
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
        $selectScript =
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
                var tpl = $('template.produkVarian-tpl');
                index++;
                var template = tpl.html().replace(/__LA_KEY__/g, index);
                $(template).find('input#hargajual')[0].value = hargaJual;
                $(template).find('input#default_hargabeli')[0].value = hargaBeli;
                $('.has-many-produkVarian-forms').append(template);
                $('.produkVarian.hargajual').inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
                $('.produkVarian.default_hargabeli').inputmask({"alias":"currency","radixPoint":".","prefix":"","removeMaskOnSubmit":true});
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
        Admin::script($selectScript);
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->createProdukForm($request->all()));
    }
    public function editProduk(Content $content, Request $request, $id)
    {
        $style = 
<<<STYLE
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
            [id^="has-many-"] .form-group:has(.add) {
                width: 100%;
                position: -webkit-sticky;
                position: sticky;
                left: 0px;
                background: white;
                z-index: 10;
            }
STYLE;
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
