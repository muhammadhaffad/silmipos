<?php

namespace App\Admin\Actions\Grid;

use Encore\Admin\Actions\RowAction;

class Show extends RowAction
{
    /**
     * @return array|null|string
     */
    public function name()
    {
        return __('admin.show');
    }

    /**
     * @return string
     */
    public function href()
    {
        return "{$this->getResource()}/detail/{$this->getKey()}";
    }
}
