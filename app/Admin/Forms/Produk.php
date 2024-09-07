<?php

namespace App\Admin\Forms;

use Encore\Admin\Widgets\Form;
use Encore\Admin\Widgets\Tab;
use Illuminate\Http\Request;

class Produk extends Form
{
    /**
     * The form title.
     *
     * @var string
     */
    public $title = 'Produk';

    /**
     * Handle the form request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request)
    {
        //dump($request->all());

        admin_success('Processed successfully.');

        return back();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $tab = new Tab();
        $tab->add('Informasi Produk', $this->text('nama', __('Nama Produk')));
        $tab->add('Informasi Produk', $this->text('nama', __('Nama Produk')));
        // $this->tab('Informasi Produk', function (Form $form) {
        //     $form->text('nama', __('Nama produk'))->withoutIcon();
        //     $form->ckeditor('instok');
        //     $form->checkbox('in_stok', '')->options([1 => 'Produk di-stok']);
        // })->tab('Akuntansi', function (Form $form) {
        //     $form->select('default_akunpersediaan', __('Akun persediaan'))
        //         ->addElementClass('select2')
        //         ->attribute('data-url', route('admin.ajax.akun'))
        //         ->ajax(route(admin_get_route('ajax.akun')))
        //         ->default('1301');
    
        //     $form->select('default_akunpemasukan', __('Akun pemasukan'))
        //         ->addElementClass('select2')
        //         ->attribute('data-url', route('admin.ajax.akun'))
        //         ->ajax(route(admin_get_route('ajax.akun')))
        //         ->default('4001');
            
        //     $form->select('default_akunbiaya', __('Akun Biaya'))
        //         ->addElementClass('select2')
        //         ->attribute('data-url', route('admin.ajax.akun'))
        //         ->ajax(route(admin_get_route('ajax.akun')))
        //         ->default('5002');
        // });
    }

    /**
     * The data of the form.
     *
     * @return array $data
     */
    public function data()
    {
        return [];
    }
}
