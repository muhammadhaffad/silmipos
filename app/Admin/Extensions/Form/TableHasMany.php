<?php
namespace App\Admin\Extensions\Form;

use Encore\Admin\Form\Field\HasMany;

class TableHasMany extends HasMany
{
    protected $views = [
        'default' => 'admin::form.hasmany',
        'tab'     => 'admin::form.hasmanytab',
        'table'   => 'admin.hasmanytable',
    ];

    public function __construct($relationName, $arguments = []) {
        return parent::__construct($relationName, $arguments);
    }

    public function render()
    {
        return parent::render();
    }
}