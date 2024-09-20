<?php

namespace App\Admin\Controllers;

use App\Admin\Form\Form;
use App\Http\Controllers\Controller;
use App\Models\Produk;
use Encore\Admin\Layout\Content;

class ProdukMutasiController extends Controller
{
    public function createProdukMutasiForm() {
        $form = new Form(new Produk);
        $form->column(1/2, function($form) {
            $form->text('test');
        });
        $form->column(1/2, function($form) {
            $form->text('testt');
        });
        return $form;
    }
    public function createProdukMutasi(Content $content) {
        return $content
            ->title('Produk')
            ->description('Tambah')
            ->body($this->createProdukMutasiForm());
    }
}
