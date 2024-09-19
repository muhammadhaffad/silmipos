<?php

namespace App\Admin\Actions\Grid;

use Encore\Admin\Actions\RowAction;

class Edit extends RowAction
{
    public $name;
    public $url;
    public function __construct($name = null, $url = null)
    {
        $this->name = $name;
        $this->url = $url;
        $this->initInteractor();
    }
    /**
     * @return array|null|string
     */
    public function name()
    {
        if ($this->name) 
            return $this->name;
        return __('admin.edit');
    }

    /**
     * @return string
     */
    public function href()
    {
        if ($this->url) 
            return $this->url;
        return "{$this->getResource()}/edit/{$this->getKey()}";
    }
}
