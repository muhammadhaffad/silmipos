<?php

namespace App\Admin\Actions\Table;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;

class InlineCancelEdit extends RowAction
{
    public $name = 'Cancel';
    public $tableName = '';

    public function __construct($tableName = '')
    {
        $this->tableName = $tableName;
    }

    public function handle(Model $model)
    {
        // $model ...

        return $this->response()->success('Success message.')->refresh();
    }

    public function href()
    {
        $currentQuery = request()->query();
        $currentQuery[$this->tableName.'_inline_edit'] = null;
        return request()->fullUrlWithQuery($currentQuery);
    }

}