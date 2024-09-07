<?php

namespace App\Admin\Forms\Produk;

use Encore\Admin\Widgets\Form;
use Illuminate\Http\Request;

class AkuntansiProduk extends Form
{
    /**
     * The form title.
     *
     * @var string
     */
    public $title = 'Akuntansi Produk';

    /**
     * Handle the form request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request)
    {
        admin_success('Processed successfully.');

        return back();
    }

    /**
     * Build a form here.
     */
    public function form()
    {
        $this->text('name')->rules('required');
        $this->email('email')->rules('email');
        $this->datetime('created_at');
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
