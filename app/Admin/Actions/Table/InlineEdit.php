<?php

namespace App\Admin\Actions\Table;

use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;

class InlineEdit extends RowAction
{
    public $name = 'Edit';
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
        // dump($this->row['kode_produkvarian']);
        $currentQuery = request()->query();
        $newQueries = [$this->tableName.'_inline_edit' => $this->getKey()];
        $allQueries = array_merge($currentQuery, $newQueries);
        return request()->fullUrlWithQuery($allQueries);
    }
}