<?php

namespace App\Admin\Actions\Grid;

use Encore\Admin\Actions\RowAction;

class Edit extends RowAction
{
    protected $name;
    protected $url;
    public function __construct()
    {
        $this->initInteractor();
    }
    /**
     * @return array|null|string
     */
    public function name()
    {
        return __('admin.edit');
    }

    /**
     * @return string
     */
    public function href()
    {
        return "{$this->getResource()}/edit/{$this->getKey()}";
    }
}
